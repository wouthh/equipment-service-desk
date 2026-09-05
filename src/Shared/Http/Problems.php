<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Infrastructure\Ids;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class Problems
{
    public function __construct(private Ids $ids)
    {
    }

    private function correlation(Request $request): string
    {
        $value = $request->attributes->get('_correlation');
        if (!is_string($value)) {
            $value = $this->ids->next();
            $request->attributes->set('_correlation', $value);
        }

        return $value;
    }

    #[AsEventListener(event: 'kernel.request', priority: 2048)]
    public function start(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $request->attributes->set('_correlation', $this->ids->next());
        if (strlen($request->getContent()) > 16384) {
            throw new Problem(413, 'body_too_large');
        }
    }

    /** @param list<array{field:string,code:string}> $violations */
    public function response(Request $request, int $status, string $code, array $violations = []): JsonResponse
    {
        $body = ['type' => 'about:blank', 'title' => JsonResponse::$statusTexts[$status] ?? 'Request failed', 'status' => $status, 'code' => $code,
            'detail' => 'The request could not be completed.', 'instance' => 'urn:uuid:'.$this->correlation($request), 'correlationId' => $this->correlation($request)];
        if ([] !== $violations) {
            $body['violations'] = $violations;
        }

        return new JsonResponse($body, $status, ['Content-Type' => 'application/problem+json', 'Cache-Control' => 'no-store']);
    }

    #[AsEventListener(event: 'kernel.exception', priority: 128)]
    public function exception(ExceptionEvent $event): void
    {
        $error = $event->getThrowable();
        [$status,$code,$violations] = match (true) {
            $error instanceof Problem => [$error->status, $error->problemCode, $error->violations],
            $error instanceof OptimisticLockException => [412, 'stale_version', []],
            $error instanceof UniqueConstraintViolationException => [409, 'conflict', []],
            $error instanceof DbalException => [503, 'storage_unavailable', []],
            $error instanceof AccessDeniedException => [403, 'forbidden', []],
            $error instanceof HttpExceptionInterface => [$error->getStatusCode(), 'http_error', []],
            default => [500, 'internal_error', []],
        };
        // Do not send exception text, previous exceptions, input or credentials.
        $event->setResponse($this->response($event->getRequest(), $status, $code, $violations));
    }

    #[AsEventListener(event: 'kernel.response')]
    public function headers(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        $correlation = $event->getRequest()->attributes->get('_correlation');
        if (is_string($correlation)) {
            $response->headers->set('X-Correlation-ID', $correlation);
        }
    }
}
