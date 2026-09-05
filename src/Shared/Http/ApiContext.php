<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Access\Domain\Principal;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

final readonly class ApiContext
{
    public function __construct(private Security $security, private Limits $limits)
    {
    }

    public function actor(): Principal
    {
        $actor = $this->security->getUser();
        if (!$actor instanceof Principal || !$actor->active()) {
            throw new Problem(401, 'unauthenticated');
        }
        $this->limits->api($actor);

        return $actor;
    }

    public function correlation(Request $request): string
    {
        $value = $request->attributes->get('_correlation');
        if (!is_string($value)) {
            throw new \LogicException('Missing request correlation.');
        }

        return $value;
    }
}
