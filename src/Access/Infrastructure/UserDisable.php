<?php

declare(strict_types=1);

namespace App\Access\Infrastructure;

use App\Access\Domain\Principal;
use App\Audit\Application\AuditWriter;
use App\Shared\Infrastructure\Store;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:user:disable', description: 'Disable a local account and all access through its tokens.')]
final class UserDisable extends Command
{
    public function __construct(private readonly Store $store, private readonly AuditWriter $audit)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('handle', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $handle = $input->getArgument('handle');
        if (!is_string($handle)) {
            return Command::INVALID;
        }
        $actor = $this->store->em()->getRepository(Principal::class)->findOneBy(['handle' => $handle]);
        if (null === $actor) {
            $output->writeln('Account not found.');

            return Command::INVALID;
        }
        if ($actor->active()) {
            $actor->disable();
            $this->audit->localOperator($actor->id(), 'local.user_disabled');
        }
        $this->store->em()->flush();
        $output->writeln('Local account disabled.');

        return Command::SUCCESS;
    }
}
