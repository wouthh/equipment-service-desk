<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Access\Domain\Principal;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class Limits
{
    public function __construct(
        #[Autowire(service: 'limiter.api')] private RateLimiterFactory $api,
        #[Autowire(service: 'limiter.reports')] private RateLimiterFactory $reports,
    ) {
    }

    public function api(Principal $actor): void
    {
        if (!$this->api->create($actor->id())->consume()->isAccepted()) {
            throw new Problem(429, 'rate_limited');
        }
    }

    public function report(Principal $actor): void
    {
        if (!$this->reports->create($actor->id())->consume()->isAccepted()) {
            throw new Problem(429, 'report_rate_limited');
        }
    }
}
