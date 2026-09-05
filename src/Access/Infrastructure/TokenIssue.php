<?php

declare(strict_types=1);

namespace App\Access\Infrastructure;

use App\Access\Domain\AccessToken;
use App\Access\Domain\Principal;
use App\Audit\Application\AuditWriter;
use App\Shared\Infrastructure\Ids;
use App\Shared\Infrastructure\Store;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:token:issue', description: 'Issue a local bearer token into a protected ignored curl configuration file.')]
final class TokenIssue extends Command
{
    public function __construct(private readonly Store $store, private readonly Ids $ids, private readonly ClockInterface $clock, private readonly AuditWriter $audit, #[Autowire('%kernel.project_dir%')] private readonly string $root)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('actor', InputArgument::REQUIRED)->addOption('ttl', null, InputOption::VALUE_REQUIRED, 'Lifetime in seconds, 1..86400', '3600');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $handle = $input->getArgument('actor');
        if (!is_string($handle)) {
            return Command::INVALID;
        }
        $ttl = filter_var($input->getOption('ttl'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 86400]]);
        $principal = $this->store->em()->getRepository(Principal::class)->findOneBy(['handle' => $handle, 'active' => true]);
        if (false === $ttl || null === $principal) {
            $output->writeln('Active actor and valid TTL required.');

            return Command::INVALID;
        }
        $directory = $this->root.'/var/local/tokens';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create protected token directory.');
        }
        if (is_link($directory) || (fileperms($directory) & 0077) !== 0) {
            throw new \RuntimeException('Token directory must be owner-only.');
        }
        $id = $this->ids->next();
        $token = bin2hex(random_bytes(32));
        $path = $directory.'/'.$id.'.curl';
        $oldMask = umask(0077);
        try {
            $file = fopen($path, 'x');
        } finally {
            umask($oldMask);
        }
        if (false === $file) {
            throw new \RuntimeException('Cannot create token file.');
        }
        try {
            $this->store->em()->wrapInTransaction(function () use ($principal, $token, $id, $ttl, $file): void {
                $this->store->em()->persist(new AccessToken($id, hash('sha256', $token), $principal, $this->clock->now()->modify('+'.$ttl.' seconds')));
                $this->audit->localOperator($id, 'local.token_issued', ['principalId' => $principal->id(), 'lifetimeSeconds' => $ttl]);
                $contents = 'header = "Authorization: Bearer '.$token."\"\n";
                if (strlen($contents) !== fwrite($file, $contents)) {
                    throw new \RuntimeException('Cannot write token file.');
                }
            });
        } catch (\Throwable $failure) {
            fclose($file);
            unlink($path);
            throw $failure;
        } finally {
            if (is_resource($file)) {
                fclose($file);
            }
        }
        $output->writeln('Token ID: '.$id);
        $output->writeln('Protected curl configuration: var/local/tokens/'.$id.'.curl');

        return Command::SUCCESS;
    }
}
