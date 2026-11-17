<?php
declare(strict_types=1);
namespace Kiosky\Core;
/** A fresh read that does not change the caller's compare-and-swap baseline. */
interface StateSnapshotReader
{
    /** @return array<string,mixed> */
    public function snapshot():array;
}
