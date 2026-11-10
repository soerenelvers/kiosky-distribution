<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Service;

use Kiosky\Core\Api;
use Kiosky\Core\CrewBrainService;
use Kiosky\Core\Crypto;
use Kiosky\Core\DwdService;
use Kiosky\Core\EasyJobService;
use Kiosky\Kiosky\Persistence\Typo3StateStore;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class ApiFactory
{
    public function __construct(
        private Typo3StateStore $store,
        private Typo3HttpClient $http,
        private ConnectionPool $connectionPool,
        private Typo3MediaLibrary $media,
    ) {
    }

    /** @param array{id:string,name:string,email:string,role:string} $identity */
    public function create(array $identity, bool $external = false): Api
    {
        $secret = (string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '');
        $crypto = new Crypto($secret);
        $backendUsers = $external ? [] : $this->connectionPool->getConnectionForTable('be_users')->fetchAllAssociative(
            'SELECT uid, username, realName, email, admin, disable, tx_kiosky_role FROM be_users WHERE deleted = 0 ORDER BY username'
        );
        $portableUsers = array_map(static function (array $user): array {
            $configured = (string)($user['tx_kiosky_role'] ?? '');
            $role = (bool)($user['admin'] ?? false) || in_array($configured, ['administrator', 'admin', 'manager'], true)
                ? 'admin'
                : (in_array($configured, ['editor', 'event_editor', 'display_operator'], true) ? 'editor' : ($configured==='designer'?'designer':'viewer'));
            return [
                'id' => 'typo3:' . $user['uid'],
                'email' => (string)($user['email'] ?: $user['username'] . '@users.kiosky.invalid'),
                'username' => (string)$user['username'],
                'displayName' => (string)($user['realName'] ?: $user['username']),
                'role' => $role,
                'status' => (bool)($user['disable'] ?? false) ? 'disabled' : 'active',
                'allLocations' => true,
                'locationIds' => [],
            ];
        }, $backendUsers);
        return new Api(
            new \Kiosky\Core\CmsMediaStateStore($this->store, $this->media, !$external && ($identity['role'] ?? 'viewer') !== 'viewer', !$external),
            $identity,
            '3.38.0',
            new CrewBrainService($this->http, $crypto),
            new DwdService($this->http),
            new EasyJobService($this->http, $crypto),
            $portableUsers,
            'typo3',
            null,
            new \Kiosky\Core\LicenseClient($crypto,'typo3','3.38.0'),
            fontStorage: new \Kiosky\Core\FontStorage($this->fontDirectory()),
            canReadFonts: !$external && ($identity['id']??'')==='typo3:'.($GLOBALS['BE_USER']->user['uid']??'') && (new \Kiosky\Kiosky\Security\PermissionService())->isGranted(\Kiosky\Kiosky\Security\PermissionService::VIEW),
            canManageFonts: !$external && ($identity['role']??'viewer')==='admin' && (new \Kiosky\Kiosky\Security\PermissionService())->isGranted(\Kiosky\Kiosky\Security\PermissionService::MANAGE_SETTINGS),
        );
    }

    private function fontDirectory(): string
    {
        $public = rtrim(\TYPO3\CMS\Core\Core\Environment::getPublicPath(), '/');
        $var = rtrim(\TYPO3\CMS\Core\Core\Environment::getVarPath(), '/');
        // Classic TYPO3 places var inside the public tree. Do not depend on
        // Apache .htaccess being honored by an nginx/Plesk static-file route.
        if ($var === $public || str_starts_with($var, $public . '/')) {
            return dirname($public) . '/private/kiosky-fonts-' . substr(hash('sha256', $public), 0, 16);
        }
        return $var . '/kiosky/fonts';
    }
}
