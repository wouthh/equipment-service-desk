<?php

declare(strict_types=1);

namespace App\Access\Infrastructure;

use App\Shared\Http\Problems;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final readonly class AuthenticationFailure implements AuthenticationFailureHandlerInterface, AuthenticationEntryPointInterface
{
    public function __construct(private Problems $problems)
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->failure($request);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->failure($request);
    }

    private function failure(Request $request): Response
    {
        $response = $this->problems->response($request, 401, 'unauthenticated');
        $response->headers->set('WWW-Authenticate', 'Bearer');

        return $response;
    }
}
