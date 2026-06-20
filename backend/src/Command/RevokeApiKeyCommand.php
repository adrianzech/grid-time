<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ApiKey;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'api-key:revoke', description: 'Revoke an API key by identifier.')]
final class RevokeApiKeyCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('identifier', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = $this->entityManager->getRepository(ApiKey::class)->findOneBy(['identifier' => $input->getArgument('identifier')]);
        if (!$key instanceof ApiKey) {
            return Command::FAILURE;
        }
        $key->revoke();
        $this->entityManager->flush();
        new SymfonyStyle($input, $output)->success('API key revoked.');

        return Command::SUCCESS;
    }
}
