<?php

namespace App\Infrastructure\Console;

use App\Application\Command\TriggerPipeline\TriggerPipelineCommand;
use App\Domain\PipelineRun\PipelineRunId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'pipeline:run', description: 'Trigger a pipeline run')]
class TriggerPipelineConsoleCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('pipeline-id', InputArgument::REQUIRED, 'Pipeline ID (YAML filename without .yaml)')
            ->addOption('env', null, InputOption::VALUE_REQUIRED, 'Environment', 'staging');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runId = PipelineRunId::generate();
        $this->bus->dispatch(new TriggerPipelineCommand(
            $runId->value,
            $input->getArgument('pipeline-id'),
            $input->getOption('env'),
        ));

        $output->writeln("Pipeline run triggered: <info>{$runId->value}</info>");
        return Command::SUCCESS;
    }
}
