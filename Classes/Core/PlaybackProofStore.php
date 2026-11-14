<?php
declare(strict_types=1);
namespace Kiosky\Core;
interface PlaybackProofStore {
 public function insert(array $rows):void;
 public function report(string $from,string $until,array $displayIds):array;
}
