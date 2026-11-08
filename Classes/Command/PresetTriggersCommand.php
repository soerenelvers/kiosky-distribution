<?php
declare(strict_types=1);
namespace Kiosky\Kiosky\Command;
use Kiosky\Kiosky\Service\ApiFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'kiosky:presets:run-due',description:'Löst fällige Preset-Termine einmalig aus, unabhängig von Datenimporten.')]
final class PresetTriggersCommand extends Command
{
    public function __construct(private readonly ApiFactory $apiFactory){parent::__construct();}
    protected function execute(InputInterface $input,OutputInterface $output): int
    {
        $summary=$this->apiFactory->create(['id'=>'typo3:preset-command','name'=>'Preset-Zeitplanung','email'=>'','role'=>'admin'],true)->runPresetTriggers();
        $output->writeln(json_encode($summary,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        return Command::SUCCESS;
    }
}
