<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsMonologProcessor]
final class SafeLogProcessor
{
    public function __construct(private readonly RequestStack $requests)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        // Framework exception contexts can contain headers, SQL and input. Keep only classification.
        $context = [];
        $correlation = $this->requests->getCurrentRequest()?->attributes->get('_correlation');
        if (is_string($correlation)) {
            $context['correlationId'] = $correlation;
        }
        $error = $record->context['exception'] ?? null;
        if ($error instanceof \Throwable) {
            $context['exceptionType'] = $error::class;
        }

        return $record->with(message: 'Application event: '.$record->level->getName(), context: $context, extra: []);
    }
}
