<?php

declare(strict_types=1);

namespace App\Access\Infrastructure;

use App\Access\Domain\AccessToken;
use App\Shared\Infrastructure\Store;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final readonly class TokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private Store $store, private ClockInterface $clock)
    {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $accessToken)) {
            throw new BadCredentialsException('Invalid access token.');
        }
        $token = $this->store->em()->getRepository(AccessToken::class)->findOneBy(['digest' => hash('sha256', $accessToken)]);
        if (null === $token || !$token->validAt($this->clock->now())) {
            throw new BadCredentialsException('Invalid access token.');
        }
        $principal = $token->principal();

        return new UserBadge($principal->id(), static fn () => $principal);
    }
}
