<?php

declare(strict_types=1);
namespace Kiosky\Core;

final class StateConflict extends \RuntimeException
{
    public function __construct() { parent::__construct('Die Kiosky-Daten wurden parallel geändert. Bitte die Anfrage wiederholen.',503); }
}
