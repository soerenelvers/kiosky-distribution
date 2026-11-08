<?php
declare(strict_types=1);
namespace Kiosky\Kiosky\Task;
use Kiosky\Kiosky\Service\ApiFactory;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
final class PresetTriggersTask extends AbstractTask
{
    public function execute(): bool
    {
        try{
            GeneralUtility::makeInstance(ApiFactory::class)->create(['id'=>'typo3:preset-scheduler','name'=>'Preset-Zeitplanung','email'=>'','role'=>'admin'],true)->runPresetTriggers();
            return true;
        }catch(Throwable){return false;}
    }
}
