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
    private const VERSION = '3.2.1';

    public function __construct(
        private ConnectionPool $connectionPool,
        private RequestFactory $requestFactory,
        private ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    /** @return array<string,mixed> */
    public function status(bool $refreshIfStale = false): array
    {
        $stored = [];
        try {
            $row = $this->connectionPool->getConnectionForTable('tx_kiosky_update_state')
                ->fetchAssociative('SELECT status_json FROM tx_kiosky_update_state WHERE uid = 1');
            $stored = $row ? json_decode((string)$row['status_json'], true, 512, JSON_THROW_ON_ERROR) : [];
        } catch (\Throwable) {
            $stored = [];
        }
        $status = array_merge([
            'installedVersion' => self::VERSION,
            'availableVersion' => null,
            'updateAvailable' => false,
            'checkedAt' => null,
            'releaseNotes' => '',
            'releaseNotesUrl' => null,
            'compatible' => true,
            'canInstall' => false,
            'installationMode' => $this->isComposerInstallation() ? 'composer' : 'classic',
            'lastError' => null,
            'stagedVersion' => null,
            'restartRequired' => false,
        ], is_array($stored) ? $stored : [], ['installedVersion' => self::VERSION]);
        if ($refreshIfStale) {
            $checkedAt = isset($status['checkedAt']) ? strtotime((string)$status['checkedAt']) : false;
            if ($checkedAt === false || $checkedAt < time() - 86400) return $this->check();
        }
        return $status;
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
                } elseif (!in_array($url->getHost(), ['github.com', 'raw.githubusercontent.com'], true)) {
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
            $composerInstall = $this->isComposerInstallation();
            $packageAvailable = $available && $composerInstall
                ? $this->composerVersionAvailable((string)$manifest['version'])
                : false;
            return $this->save(array_merge($status, [
                'availableVersion' => (string)$manifest['version'],
                'updateAvailable' => $available,
                'checkedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'releaseNotes' => (string)($manifest['release_notes'] ?? ''),
                'releaseNotesUrl' => isset($manifest['release_notes_url']) ? (string)$manifest['release_notes_url'] : null,
                'compatible' => true,
                'canInstall' => $available && $composerInstall && $packageAvailable,
                'installationMode' => $composerInstall ? 'composer' : 'classic',
                'lastError' => $available && !$composerInstall
                    ? 'One-Click-Updates benötigen eine Composer-Installation. Das Classic-ZIP kann im TYPO3 Extension Manager installiert werden.'
                    : ($available && !$packageAvailable
                        ? 'Release ' . (string)$manifest['version'] . ' ist im konfigurierten Composer-Repository noch nicht verfügbar.'
                        : null),
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
        if (!$status['canInstall']) throw new RuntimeException((string)($status['lastError'] ?: 'Das Composer-Paket ist noch nicht installierbar.'));

        $configuration = $this->configuration();
        $project = Environment::getProjectPath();
        $composerJson = $project . '/composer.json';
        $composerLock = $project . '/composer.lock';
        $backup = Environment::getVarPath() . '/kiosky-update-backups/' . date('Ymd-His');
        if (!is_dir($backup) && !mkdir($backup, 0750, true) && !is_dir($backup)) throw new RuntimeException('Update-Sicherung konnte nicht angelegt werden.');
        copy($composerJson, $backup . '/composer.json');
        if (is_file($composerLock)) copy($composerLock, $backup . '/composer.lock');

        $composer = $this->composerBinary($configuration);
        $composerEnvironment = $this->composerEnvironment($configuration);
        $targetVersion = (string)$status['availableVersion'];
        try {
            $this->run([$composer, 'update', 'casesound/kiosky', '--with-all-dependencies', '--no-interaction', '--no-progress', '--prefer-dist'], $project, 900, $composerEnvironment);
            $installedVersion = $this->installedComposerVersion($composer, $project, $composerEnvironment);
            if ($installedVersion !== $targetVersion) {
                throw new RuntimeException('Composer installierte ' . ($installedVersion ?: 'keine Version') . ' statt ' . $targetVersion . '. Bitte den Versionsbereich in composer.json prüfen.');
            }
            $typo3 = $project . '/vendor/bin/typo3';
            if (!is_file($typo3)) throw new RuntimeException('TYPO3-Konsolenprogramm wurde nicht gefunden.');
            $this->run([PHP_BINARY, $typo3, 'extension:setup', '--extension=kiosky'], $project, 300);
            $this->run([PHP_BINARY, $typo3, 'cache:flush'], $project, 300);
        } catch (\Throwable $error) {
            copy($backup . '/composer.json', $composerJson);
            if (is_file($backup . '/composer.lock')) copy($backup . '/composer.lock', $composerLock);
            try {
                $this->run([$composer, 'install', '--no-interaction', '--no-progress', '--prefer-dist'], $project, 900, $composerEnvironment);
            } catch (\Throwable $rollbackError) {
                throw new RuntimeException('Update und automatische Wiederherstellung sind fehlgeschlagen. Sicherung: ' . $backup . '. ' . $error->getMessage() . ' Rollback: ' . $rollbackError->getMessage(), 0, $error);
            }
            throw new RuntimeException('Update fehlgeschlagen; Composer-Dateien und installierte Pakete wurden wiederhergestellt. ' . $error->getMessage(), 0, $error);
        }
        return $this->save(array_merge($status, [
            'updateAvailable' => false,
            'canInstall' => false,
            'lastError' => null,
            'restartRequired' => false,
            'installedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'installedVersion' => $targetVersion,
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
        if (!str_starts_with((string)($component['download_url'] ?? ''), 'https://')) throw new RuntimeException('Das TYPO3-Release besitzt keine sichere Download-Adresse.');
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

    /** @param array<string,mixed> $configuration */
    private function composerBinary(array $configuration): string
    {
        $composer = trim((string)($configuration['updateComposerBinary'] ?? 'composer')) ?: 'composer';
        if (!preg_match('/^[A-Za-z0-9._\/\\\\:-]+$/', $composer)) throw new RuntimeException('Der konfigurierte Composer-Pfad ist ungültig.');
        return $composer;
    }

    /** @param array<string,mixed> $configuration @return array<string,string>|null */
    private function composerEnvironment(array $configuration): ?array
    {
        $token = trim((string)($configuration['updateAccessToken'] ?? ''));
        if ($token === '') return null;
        $auth = [];
        $configured = getenv('COMPOSER_AUTH');
        if (is_string($configured) && $configured !== '') {
            try {
                $decoded = json_decode($configured, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) $auth = $decoded;
            } catch (\Throwable) {
                $auth = [];
            }
        }
        $auth['github-oauth']['github.com'] = $token;
        return ['COMPOSER_AUTH' => json_encode($auth, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)];
    }

    private function composerVersionAvailable(string $version): bool
    {
        try {
            $project = Environment::getProjectPath();
            $configuration = $this->configuration();
            $composer = $this->composerBinary($configuration);
            $process = $this->process([$composer, 'show', 'casesound/kiosky', '--all', '--format=json', '--no-interaction'], $project, 120, $this->composerEnvironment($configuration));
            $data = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            $versions = is_array($data['versions'] ?? null) ? $data['versions'] : [];
            return in_array($version, array_map(fn(mixed $item): string => $this->normalizeComposerVersion($item), $versions), true);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @param array<string,string>|null $environment */
    private function installedComposerVersion(string $composer, string $project, ?array $environment): string
    {
        $process = $this->process([$composer, 'show', 'casesound/kiosky', '--format=json', '--no-interaction'], $project, 120, $environment);
        $data = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $versions = is_array($data['versions'] ?? null) ? $data['versions'] : [];
        return $this->normalizeComposerVersion($versions[0] ?? '');
    }

    private function normalizeComposerVersion(mixed $version): string
    {
        return (string)preg_replace('/^\*?\s*v?/', '', trim((string)$version));
    }

    /** @param list<string> $command @param array<string,string>|null $environment */
    private function run(array $command, string $workingDirectory, int $timeout, ?array $environment = null): void
    {
        $this->process($command, $workingDirectory, $timeout, $environment);
    }

    /** @param list<string> $command @param array<string,string>|null $environment */
    private function process(array $command, string $workingDirectory, int $timeout, ?array $environment = null): Process
    {
        $process = new Process($command, $workingDirectory, $environment, null, $timeout);
        $process->run();
        if (!$process->isSuccessful()) throw new RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'Externer Update-Schritt fehlgeschlagen.');
        return $process;
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
