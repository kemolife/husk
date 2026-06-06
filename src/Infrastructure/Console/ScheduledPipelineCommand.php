<?php

namespace App\Infrastructure\Console;

use App\Application\Command\TriggerPipeline\TriggerPipelineCommand;
use App\Domain\PipelineRun\PipelineRunId;
use App\Infrastructure\Persistence\Yaml\YamlFilePipelineRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'pipeline:schedule-check', description: 'Trigger pipelines whose cron schedule is due')]
class ScheduledPipelineCommand extends Command
{
    public function __construct(
        private readonly YamlFilePipelineRepository $pipelineRepo,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable();

        foreach ($this->pipelineRepo->findAll() as $pipeline) {
            foreach ($pipeline->schedules() as $schedule) {
                if (!$schedule->isDue($now)) {
                    continue;
                }

                $runId = PipelineRunId::generate();
                $this->bus->dispatch(new TriggerPipelineCommand(
                    $runId->value,
                    $pipeline->id()->value,
                    $schedule->environment,
                ));

                $output->writeln(sprintf(
                    'Triggered <info>%s</info> [%s] → run <comment>%s</comment>',
                    $pipeline->id()->value,
                    $schedule->environment,
                    $runId->value,
                ));
            }
        }

        return Command::SUCCESS;
    }
}
