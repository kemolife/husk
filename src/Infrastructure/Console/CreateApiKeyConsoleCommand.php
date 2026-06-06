<?php

namespace App\Infrastructure\Console;

use App\Domain\Auth\ApiKey;
use App\Infrastructure\Persistence\Doctrine\DoctrineApiKeyRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(name: 'api-key:create', description: 'Generate and store an API key')]
class CreateApiKeyConsoleCommand extends Command
{
    public function __construct(private readonly DoctrineApiKeyRepository $repo)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Human-readable label for this key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $plainKey = bin2hex(random_bytes(32));
        $apiKey = new ApiKey(Uuid::v4()->toRfc4122(), $plainKey, $input->getArgument('name'));
        $this->repo->save($apiKey);

        $output->writeln("API key created:");
        $output->writeln("  Name: <info>{$input->getArgument('name')}</info>");
        $output->writeln("  Key:  <comment>{$plainKey}</comment>");
        $output->writeln("<fg=yellow>Store this key — it will not be shown again.</>");

        return Command::SUCCESS;
    }
}
