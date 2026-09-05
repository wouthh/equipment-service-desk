<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

#[AsEventListener(event: 'kernel.exception', priority: 256)]
final class ExceptionProbe
{
    public function __invoke(ExceptionEvent $event): void
    {
        $error = $event->getThrowable();
        if ($error instanceof \App\Shared\Http\Problem || $error instanceof \Symfony\Component\Security\Core\Exception\AuthenticationException || $error instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return;
        }
        $frames = [];
        foreach (array_slice($error->getTrace(), 0, 5) as $frame) {
            $frames[] = ['file' => $frame['file'] ?? '', 'line' => $frame['line'] ?? 0, 'function' => $frame['function']];
        }
        fwrite(STDERR, json_encode(['exception' => $error::class, 'code' => $error->getCode(), 'file' => $error->getFile(), 'line' => $error->getLine(), 'frames' => $frames], JSON_THROW_ON_ERROR)."\n");
    }
}
