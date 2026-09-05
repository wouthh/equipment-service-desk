<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

final readonly class ConfigurationGuard
{
    public function __construct(
        #[Autowire('%env(APP_SECRET)%')] private string $secret,
        #[Autowire('%env(DATABASE_URL)%')] private string $databaseUrl,
    ) {
    }

    public function validate(): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $this->secret)) {
            throw new \RuntimeException('APP_SECRET must be generated local configuration.');
        }
        $parts = parse_url($this->databaseUrl);
        if (false === $parts || !in_array($parts['scheme'] ?? '', ['postgresql', 'postgres'], true) || empty($parts['host']) || empty($parts['user']) || !preg_match('/^[a-f0-9]{64}$/D', $parts['pass'] ?? '')) {
            throw new \RuntimeException('DATABASE_URL must use the generated local configuration.');
        }
        if ('db' !== $parts['host'] || !in_array($parts['user'], ['desk_runtime', 'desk_migrator'], true) || ($parts['port'] ?? 5432) !== 5432
            || !preg_match('~^/desk(?:_test|_upgrade_[0-9]+)?$~D', $parts['path'] ?? '')
            || isset($parts['fragment']) || !in_array($parts['query'] ?? '', ['', 'serverVersion=18&charset=utf8'], true)) {
            throw new \RuntimeException('Only the isolated local database configuration is supported.');
        }
    }

    #[AsEventListener(event: 'kernel.request', priority: 1024)]
    public function http(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->validate();
        }
    }

    #[AsEventListener(event: 'console.command')]
    public function console(ConsoleCommandEvent $event): void
    {
        $this->validate();
    }
}
