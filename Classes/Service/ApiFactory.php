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
    ) {
    }

    /** @param array{id:string,name:string,email:string,role:string} $identity */
    public function create(array $identity): Api
    {
        $secret = (string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '');
        $crypto = new Crypto($secret);
        $backendUsers = $this->connectionPool->getConnectionForTable('be_users')->fetchAllAssociative(
            'SELECT uid, username, realName, email, admin, disable, tx_kiosky_role FROM be_users WHERE deleted = 0 ORDER BY username'
        );
        $portableUsers = array_map(static function (array $user): array {
            $configured = (string)($user['tx_kiosky_role'] ?? '');
            $role = (bool)($user['admin'] ?? false) || in_array($configured, ['administrator', 'admin', 'manager'], true)
                ? 'admin'
                : (in_array($configured, ['editor', 'event_editor', 'display_operator'], true) ? 'editor' : 'viewer');
            return [
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
            $this->store,
            $identity,
            '3.1.3',
            new CrewBrainService($this->http, $crypto),
            new DwdService($this->http),
            new EasyJobService($this->http, $crypto),
            $portableUsers,
            'typo3',
        );
    }
}
