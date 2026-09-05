<?php

declare(strict_types=1);

namespace App\Access\Infrastructure;

use App\Access\Domain\Principal;
use App\Access\Domain\Role;
use App\Audit\Application\AuditWriter;
use App\Shared\Infrastructure\Ids;
use App\Shared\Infrastructure\Store;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:user:create', description: 'Provision a single-role local account; no external identity service.')]
final class UserCreate extends Command
{
    public function __construct(private readonly Store $store, private readonly Ids $ids, private readonly AuditWriter $audit)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('handle', InputArgument::REQUIRED)->addArgument('role', InputArgument::REQUIRED)->addArgument('label', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $handle = $input->getArgument('handle');
        $role = $input->getArgument('role');
        $label = $input->getArgument('label');
        if (!is_string($handle) || !preg_match('/^[a-z][a-z0-9-]{1,47}$/D', $handle) || !is_string($role) || null === ($role = Role::tryFrom($role)) || !is_string($label) || '' === trim($label) || mb_strlen($label) > 80) {
            $output->writeln('Valid handle, role and bounded label required.');

            return Command::INVALID;
        }
        $principal = new Principal($this->ids->next(), $handle, trim($label), $role);
        $this->store->em()->persist($principal);
        $this->audit->localOperator($principal->id(), 'local.user_created', ['role' => $role->value]);
        $this->store->em()->flush();
        $output->writeln('Local account created: '.$principal->id());

        return Command::SUCCESS;
    }
}
