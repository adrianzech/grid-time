<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ApiKeyManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'api-key:revoke', description: 'Revoke an API key by identifier.')]
final class RevokeApiKeyCommand extends Command
{
    public function __construct(private readonly ApiKeyManager $apiKeyManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('identifier', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->apiKeyManager->revoke((string) $input->getArgument('identifier'))) {
            return Command::FAILURE;
        }
        new SymfonyStyle($input, $output)->success('API key revoked.');

        return Command::SUCCESS;
    }
}
