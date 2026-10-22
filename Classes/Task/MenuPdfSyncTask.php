<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Task;

use Kiosky\Kiosky\Service\ApiFactory;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

final class MenuPdfSyncTask extends AbstractTask
{
    public function execute(): bool
    {
        try {
            GeneralUtility::makeInstance(ApiFactory::class)
                ->create(['id' => 'typo3:scheduler', 'name' => 'TYPO3 Scheduler', 'email' => '', 'role' => 'admin'])
                ->runMenuSubscriptions();
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
