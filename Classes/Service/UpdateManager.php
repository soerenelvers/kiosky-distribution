<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Service;

use RuntimeException;
use Symfony\Component\Process\Process;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\RequestFactory;

final readonly class UpdateManager
{
    private const VERSION = '3.1.3';

    public function __construct(
        private ConnectionPool $connectionPool,
        private RequestFactory $requestFactory,
        private ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    /** @return array<string,mixed> */
    public function status(): array
    {
        $stored = [];
        try {
            $row = $this->connectionPool->getConnectionForTable('tx_kiosky_update_state')
                ->fetchAssociative('SELECT status_json FROM tx_kiosky_update_state WHERE uid = 1');
            $stored = $row ? json_decode((string)$row['status_json'], true, 512, JSON_THROW_ON_ERROR) : [];
        } catch (\Throwable) {
            $stored = [];
        }
        return array_merge([
            'installedVersion' => self::VERSION,
            'availableVersion' => null,
            'updateAvailable' => false,
            'checkedAt' => null,
            'releaseNotes' => '',
            'releaseNotesUrl' => null,
            'compatible' => true,
            'canInstall' => $this->isComposerInstallation(),
            'installationMode' => $this->isComposerInstallation() ? 'composer' : 'classic',
            'lastError' => null,
            'stagedVersion' => null,
            'restartRequired' => false,
        ], is_array($stored) ? $stored : [], ['installedVersion' => self::VERSION]);
    }

    /** @return array<string,mixed> */
    public function check(): array
    {
        $status = $this->status();
        try {
            $configuration = $this->configuration();
            $url = $this->secureUrl((string)($configuration['updateManifestUrl'] ?? ''));
            $headers = ['Accept' => 'application/json', 'User-Agent' => 'Kiosky/' . self::VERSION . ' TYPO3-Updater'];
            $token = trim((string)($configuration['updateAccessToken'] ?? ''));
            if ($token !== '') {
                if ($url->getHost() === 'api.github.com') {
                    $headers += ['Authorization' => 'Bearer ' . $token, 'X-GitHub-Api-Version' => '2022-11-28'];
                    $headers['Accept'] = 'application/vnd.github.raw+json';
                } else {
                    $headers['X-Kiosky-License'] = $token;
                }
            }
            $response = $this->requestFactory->request((string)$url, 'GET', [
                'headers' => $headers,
                'timeout' => 30,
                'verify' => true,
                'allow_redirects' => ['max' => 0],
            ]);
            if ($response->getStatusCode() !== 200) throw new RuntimeException('Update-Server antwortet mit HTTP ' . $response->getStatusCode() . '.');
            $manifest = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $this->validateManifest($manifest);
            $available = version_compare((string)$manifest['version'], self::VERSION, '>');
            return $this->save(array_merge($status, [
                'availableVersion' => (string)$manifest['version'],
                'updateAvailable' => $available,
                'checkedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'releaseNotes' => (string)($manifest['release_notes'] ?? ''),
                'releaseNotesUrl' => isset($manifest['release_notes_url']) ? (string)$manifest['release_notes_url'] : null,
                'compatible' => true,
                'canInstall' => $available && $this->isComposerInstallation(),
                'installationMode' => $this->isComposerInstallation() ? 'composer' : 'classic',
                'lastError' => $available && !$this->isComposerInstallation()
                    ? 'One-Click-Updates benötigen eine Composer-Installation. Die Update-Prüfung funktioniert weiterhin.'
                    : null,
            ]));
        } catch (\Throwable $error) {
            return $this->save(array_merge($status, [
                'checkedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'compatible' => false,
                'canInstall' => false,
                'lastError' => $error->getMessage(),
            ]));
        }
    }

    /** @return array<string,mixed> */
    public function install(): array
    {
        $status = $this->check();
        if (!$status['updateAvailable']) return $status;
        if (!$this->isComposerInstallation()) throw new RuntimeException('One-Click-Updates sind nur in Composer-basierten TYPO3-Installationen verfügbar.');

        $configuration = $this->configuration();
        $project = Environment::getProjectPath();
        $composerJson = $project . '/composer.json';
        $composerLock = $project . '/composer.lock';
        $backup = Environment::getVarPath() . '/kiosky-update-backups/' . date('Ymd-His');
        if (!is_dir($backup) && !mkdir($backup, 0750, true) && !is_dir($backup)) throw new RuntimeException('Update-Sicherung konnte nicht angelegt werden.');
        copy($composerJson, $backup . '/composer.json');
        if (is_file($composerLock)) copy($composerLock, $backup . '/composer.lock');

        $composer = trim((string)($configuration['updateComposerBinary'] ?? 'composer')) ?: 'composer';
        if (!preg_match('/^[A-Za-z0-9._\/\\\\:-]+$/', $composer)) throw new RuntimeException('Der konfigurierte Composer-Pfad ist ungültig.');
        try {
            $this->run([$composer, 'update', 'casesound/kiosky', '--with-dependencies', '--no-interaction', '--no-progress', '--prefer-dist'], $project, 900);
            $typo3 = $project . '/vendor/bin/typo3';
            if (!is_file($typo3)) throw new RuntimeException('TYPO3-Konsolenprogramm wurde nicht gefunden.');
            $this->run([PHP_BINARY, $typo3, 'extension:setup', '--extension=kiosky'], $project, 300);
            $this->run([PHP_BINARY, $typo3, 'cache:flush'], $project, 300);
        } catch (\Throwable $error) {
            copy($backup . '/composer.json', $composerJson);
            if (is_file($backup . '/composer.lock')) copy($backup . '/composer.lock', $composerLock);
            throw new RuntimeException('Update fehlgeschlagen; Composer-Dateien wurden wiederhergestellt. ' . $error->getMessage(), 0, $error);
        }
        return $this->save(array_merge($status, [
            'updateAvailable' => false,
            'canInstall' => false,
            'lastError' => null,
            'restartRequired' => false,
            'installedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'backupDirectory' => $backup,
        ]));
    }

    /** @param array<string,mixed> $manifest */
    public function validateManifest(array $manifest): void
    {
        $component = $manifest['components']['typo3'] ?? null;
        if (($manifest['schema_version'] ?? null) !== 1 || ($manifest['product'] ?? null) !== 'kioski' || ($manifest['channel'] ?? null) !== 'stable') {
            throw new RuntimeException('Das Update-Manifest ist ungültig.');
        }
        $version = (string)($manifest['version'] ?? '');
        if (!preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?$/', $version)
            || !is_array($component) || ($component['version'] ?? null) !== $version
            || !preg_match('/^[a-f0-9]{64}$/i', (string)($component['sha256'] ?? ''))) {
            throw new RuntimeException('Version oder TYPO3-Paket im Update-Manifest ist ungültig.');
        }
        if (($manifest['compatibility']['api_version'] ?? '1') !== '1') throw new RuntimeException('Dieses Update benötigt eine nicht unterstützte Kiosky-API.');
        if (version_compare($version, self::VERSION, '<')) throw new RuntimeException('Ein Downgrade wird nicht automatisch installiert.');
    }

    /** @return array<string,mixed> */
    private function configuration(): array
    {
        try { return $this->extensionConfiguration->get('kiosky'); }
        catch (\Throwable) { return []; }
    }

    private function secureUrl(string $value): \GuzzleHttp\Psr7\Uri
    {
        if ($value === '') throw new RuntimeException('Keine Update-Quelle konfiguriert.');
        $url = new \GuzzleHttp\Psr7\Uri($value);
        if ($url->getScheme() !== 'https' || $url->getUserInfo() !== '' || $url->getFragment() !== '') throw new RuntimeException('Die Update-Adresse muss eine HTTPS-URL ohne Zugangsdaten sein.');
        return $url;
    }

    private function isComposerInstallation(): bool
    {
        return is_file(Environment::getProjectPath() . '/composer.json')
            && is_file(Environment::getProjectPath() . '/vendor/bin/typo3');
    }

    /** @param list<string> $command */
    private function run(array $command, string $workingDirectory, int $timeout): void
    {
        $process = new Process($command, $workingDirectory, null, null, $timeout);
        $process->run();
        if (!$process->isSuccessful()) throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'Externer Update-Schritt fehlgeschlagen.');
    }

    /** @param array<string,mixed> $status @return array<string,mixed> */
    private function save(array $status): array
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_kiosky_update_state');
        $values = ['status_json' => json_encode($status, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), 'updated_at' => time()];
        $exists = (bool)$connection->fetchOne('SELECT uid FROM tx_kiosky_update_state WHERE uid = ?', [1]);
        if ($exists) $connection->update('tx_kiosky_update_state', $values, ['uid' => 1]);
        else $connection->insert('tx_kiosky_update_state', ['uid' => 1] + $values);
        return $status;
    }
}
