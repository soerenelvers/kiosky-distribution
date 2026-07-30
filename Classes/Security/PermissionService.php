<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Security;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

final class PermissionService
{
    public const VIEW = 'view';
    public const MANAGE_DISPLAYS = 'manage_displays';
    public const MANAGE_CONTENT = 'manage_content';
    public const MANAGE_EVENTS = 'manage_events';
    public const CONTROL_DISPLAYS = 'control_displays';
    public const TRIGGER_EMERGENCY = 'trigger_emergency';
    public const MANAGE_SETTINGS = 'manage_settings';
    public const VIEW_LOGS = 'view_logs';

    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'administrator' => ['*'],
        'manager' => [
            self::VIEW, self::MANAGE_DISPLAYS, self::MANAGE_CONTENT, self::MANAGE_EVENTS,
            self::CONTROL_DISPLAYS, self::TRIGGER_EMERGENCY, self::VIEW_LOGS,
        ],
        'editor' => [self::VIEW, self::MANAGE_CONTENT],
        'event_editor' => [self::VIEW, self::MANAGE_EVENTS],
        'display_operator' => [self::VIEW, self::CONTROL_DISPLAYS],
        'viewer' => [self::VIEW],
        // Compatibility with releases before 2.4.
        'admin' => ['*'],
    ];

    public function isGranted(string $permission, ?BackendUserAuthentication $user = null): bool
    {
        $user ??= $GLOBALS['BE_USER'] ?? null;
        if (!$user instanceof BackendUserAuthentication) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }
        $role = (string)($user->user['tx_kiosky_role'] ?? '');
        $permissions = self::ROLE_PERMISSIONS[$role] ?? [];
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function require(string $permission, ?BackendUserAuthentication $user = null): void
    {
        if (!$this->isGranted($permission, $user)) {
            throw new \RuntimeException('Für diese Kiosky-Aktion fehlt die Berechtigung.', 1710004030);
        }
    }
}
