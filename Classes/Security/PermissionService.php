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

    /** Approved role matrix: check native permissions before the shared API role mapping. */
    public function isApiGranted(string $method, string $path, ?BackendUserAuthentication $user = null): bool
    {
        if (!$this->isGranted(self::VIEW, $user)) return false;
        $path = '/' . ltrim((string)(parse_url($path, PHP_URL_PATH) ?: ''), '/');
        $read = in_array(strtoupper($method), ['GET', 'HEAD'], true);
        $matches = static fn(string $prefix): bool => $path === $prefix || str_starts_with($path, $prefix . '/');
        if ($path === '/api/settings/features' && $read) return true;
        foreach (['/api/settings','/api/integrations','/api/api-users','/api/users','/api/content-transfer','/api/updates','/api/v1','/api/advertising'] as $prefix) {
            if ($matches($prefix)) return $this->isGranted(self::MANAGE_SETTINGS, $user);
        }
        if ($matches('/api/audit-logs')) return $this->isGranted(self::VIEW_LOGS, $user);
        if ($matches('/api/menus/access')) return $this->isGranted(self::MANAGE_SETTINGS, $user);
        if ($path === '/api/auth/logout' && strtoupper($method) === 'POST') return true;
        // Explicit kitchen/card grants remain enforced by the shared menu API.
        if ($matches('/api/menus')) return true;
        if ($read) return true;
        if (preg_match('#^/api/operations/displays/[^/]+/commands$#', $path)) return $this->isGranted(self::CONTROL_DISPLAYS, $user);
        if (preg_match('#^/api/trash/(preset|warning|warning-template|preset-trigger)(?:/|$)#', $path)) return $this->isGranted(self::TRIGGER_EMERGENCY, $user);
        if (preg_match('#^/api/trash/(event|event-template)(?:/|$)#', $path)) return $this->isGranted(self::MANAGE_EVENTS, $user);
        if (preg_match('#^/api/(warnings|warning-templates|presets|preset-triggers|dwd|operations)(?:/|$)#', $path)) return $this->isGranted(self::TRIGGER_EMERGENCY, $user);
        if (preg_match('#^/api/(events|event-templates)(?:/|$)#', $path)) return $this->isGranted(self::MANAGE_EVENTS, $user);
        if (preg_match('#^/api/displays/[^/]+/(commands|identify|refresh|fullscreen)(?:/|$)#', $path) || preg_match('#^/api/(assignments|schedules|schedule-target-order)(?:/|$)#', $path)) return $this->isGranted(self::CONTROL_DISPLAYS, $user);
        if (preg_match('#^/api/(displays|display-groups|groups|locations|matrix-displays|player-pairings)(?:/|$)#', $path)) return $this->isGranted(self::MANAGE_DISPLAYS, $user);
        if (preg_match('#^/api/trash/(display|display-group|group|location|matrix-display)(?:/|$)#', $path)) return $this->isGranted(self::MANAGE_DISPLAYS, $user);
        if (preg_match('#^/api/(slides|templates|channels|media|content-packages|trash)(?:/|$)#', $path)) return $this->isGranted(self::MANAGE_CONTENT, $user);
        return $this->isGranted(self::MANAGE_SETTINGS, $user);
    }

    public function require(string $permission, ?BackendUserAuthentication $user = null): void
    {
        if (!$this->isGranted($permission, $user)) {
            throw new \RuntimeException('Für diese Kiosky-Aktion fehlt die Berechtigung.', 1710004030);
        }
    }
}
