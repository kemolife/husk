<?php

namespace App\Infrastructure\Console;

use App\Application\Query\GetPipelineRunStatus\GetPipelineRunStatusQuery;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[AsCommand(name: 'pipeline:status', description: 'Get pipeline run status')]
class GetPipelineRunConsoleCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('run-id', InputArgument::REQUIRED, 'Pipeline run ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $envelope = $this->bus->dispatch(new GetPipelineRunStatusQuery($input->getArgument('run-id')));
        $view = $envelope->last(HandledStamp::class)->getResult();

        $output->writeln("Run: <info>{$view->id}</info> | Status: <comment>{$view->status}</comment> | Env: {$view->environment}");
        foreach ($view->jobs as $job) {
            $output->writeln("  [{$job->status}] {$job->jobId}");
        }

        return Command::SUCCESS;
    }
}
