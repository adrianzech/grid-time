<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ApiKey;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'api-key:list', description: 'List API keys without secrets.')]
final class ListApiKeysCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = array_map(static fn (ApiKey $key): array => [$key->getIdentifier(), $key->getLabel(), $key->getCreatedAt()->format(DATE_ATOM), $key->getLastUsedAt()?->format(DATE_ATOM) ?? 'never', $key->isRevoked() ? 'revoked' : 'active'], $this->entityManager->getRepository(ApiKey::class)->findBy([], ['id' => 'ASC']));
        new SymfonyStyle($input, $output)->table(['Identifier', 'Label', 'Created', 'Last used', 'Status'], $rows);

        return Command::SUCCESS;
    }
}
