<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ApiKeyManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'api-key:create', description: 'Create a read-only API key.')]
final class CreateApiKeyCommand extends Command
{
    public function __construct(private readonly ApiKeyManager $apiKeyManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('label', InputArgument::REQUIRED, 'Client label')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Requests per minute', '120')
            ->addOption('internal', null, InputOption::VALUE_NONE, 'Create an internal first-party key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($limit === false) {
            return Command::INVALID;
        }
        $result = $this->apiKeyManager->create((string) $input->getArgument('label'), $limit, (bool) $input->getOption('internal'));
        (new SymfonyStyle($input, $output))->success(sprintf("Store this key now; it cannot be shown again:\n%s", $result['token']));

        return Command::SUCCESS;
    }
}
