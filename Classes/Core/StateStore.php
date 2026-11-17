<?php

declare(strict_types=1);

namespace Kiosky\Core;
require_once __DIR__.'/StateSnapshotReader.php';

interface StateStore
{
    /** @return array<string,mixed> */
    public function load(): array;

    /** @param array<string,mixed> $state */
    public function save(array $state): void;
}
