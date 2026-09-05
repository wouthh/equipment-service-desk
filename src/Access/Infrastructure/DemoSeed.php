<?php

declare(strict_types=1);

namespace App\Access\Infrastructure;

use App\Access\Domain\Principal;
use App\Access\Domain\Role;
use App\Equipment\Domain\Criticality;
use App\Equipment\Domain\Equipment;
use App\Shared\Infrastructure\Store;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:demo:seed', description: 'Idempotently add original synthetic fixtures in dev/test only; never purge.')]
final class DemoSeed extends Command
{
    public function __construct(private readonly Store $store, #[Autowire('%kernel.environment%')] private readonly string $environment)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!in_array($this->environment, ['dev', 'test'], true)) {
            $output->writeln('Fixture loading is restricted to dev/test.');

            return Command::FAILURE;
        }
        $this->seed();
        $output->writeln('Synthetic fixture set verified; no existing record changed.');

        return Command::SUCCESS;
    }

    public function seed(): void
    {
        if (!in_array($this->environment, ['dev', 'test'], true)) {
            throw new \LogicException('Fixtures unavailable.');
        }
        $em = $this->store->em();
        $em->wrapInTransaction(function () use ($em): void {
            $index = 1;
            foreach (Role::cases() as $role) {
                foreach (['a', 'b'] as $suffix) {
                    $id = sprintf('10000000-0000-7000-8000-%012d', $index++);
                    $handle = $role->value.'-'.$suffix;
                    $existing = $em->find(Principal::class, $id);
                    if (null === $existing) {
                        $em->persist(new Principal($id, $handle, $handle, $role));
                    } elseif ($existing->handle() !== $handle || $existing->role() !== $role || !$existing->active()) {
                        throw new \RuntimeException('Fixture identity changed; nothing overwritten.');
                    }
                }
            }
            foreach ([1 => Criticality::Normal, 2 => Criticality::Critical] as $number => $criticality) {
                $id = sprintf('20000000-0000-7000-8000-%012d', $number);
                $candidate = new Equipment($id, 'DEMO-'.$number, 'Synthetic equipment '.$number, $criticality, new \DateTimeImmutable('2026-01-01T09:00:00Z'));
                $existing = $em->find(Equipment::class, $id);
                if (null === $existing) {
                    $em->persist($candidate);
                } elseif ($existing->view() !== $candidate->view()) {
                    throw new \RuntimeException('Fixture equipment changed; nothing overwritten.');
                }
            }
        });
    }
}
