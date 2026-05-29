<?php

namespace App\Infrastructure\Console;

use App\Application\Command\ApprovePipelineJob\ApprovePipelineJobCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'pipeline:approve', description: 'Approve or reject an approval gate')]
class ApprovePipelineJobConsoleCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('run-id', InputArgument::REQUIRED, 'Pipeline run ID')
            ->addArgument('job-id', InputArgument::REQUIRED, 'Job ID of the approval gate')
            ->addArgument('decision', InputArgument::OPTIONAL, 'approve or reject', 'approve');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $approved = $input->getArgument('decision') === 'approve';
        $this->bus->dispatch(new ApprovePipelineJobCommand(
            $input->getArgument('run-id'),
            $input->getArgument('job-id'),
            $approved,
        ));

        $label = $approved ? 'approved' : 'rejected';
        $output->writeln("Job <info>{$input->getArgument('job-id')}</info> {$label}.");
        return Command::SUCCESS;
    }
}
