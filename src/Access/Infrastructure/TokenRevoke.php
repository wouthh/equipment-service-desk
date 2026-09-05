<?php

declare(strict_types=1);

namespace App\Access\Infrastructure;

use App\Access\Domain\AccessToken;
use App\Audit\Application\AuditWriter;
use App\Shared\Infrastructure\Store;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(name: 'app:token:revoke', description: 'Revoke a local application token by its non-secret ID.')]
final class TokenRevoke extends Command
{
    public function __construct(private readonly Store $store, private readonly ClockInterface $clock, private readonly AuditWriter $audit)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('id');
        if (!is_string($id) || !Uuid::isValid($id)) {
            return Command::INVALID;
        }
        $token = $this->store->em()->find(AccessToken::class, $id);
        if (null === $token) {
            $output->writeln('Token not found.');

            return Command::INVALID;
        }
        if (!$token->revoked()) {
            $token->revoke($this->clock->now());
            $this->audit->localOperator($token->id(), 'local.token_revoked');
        }
        $this->store->em()->flush();
        $output->writeln('Local application token revoked.');

        return Command::SUCCESS;
    }
}
