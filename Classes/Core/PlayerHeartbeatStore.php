<?php
declare(strict_types=1);
namespace Kiosky\Core;

/** Optional fast persistence for an already authenticated, status-only heartbeat.
 * Return false when any content/configuration/command change needs normal CAS.
 */
interface PlayerHeartbeatStore
{
    public function saveHeartbeat(array $before, array $after): bool;
}
