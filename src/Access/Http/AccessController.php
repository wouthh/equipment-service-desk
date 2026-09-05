<?php

declare(strict_types=1);

namespace App\Access\Http;

use App\Access\Application\Directory;
use App\Access\Application\Permissions;
use App\Access\Domain\Role;
use App\Shared\Http\ApiContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class AccessController
{
    public function __construct(private ApiContext $api, private Directory $directory, private Permissions $permissions)
    {
    }

    #[Route('/api/v1/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return new JsonResponse($this->api->actor()->view());
    }

    #[Route('/api/v1/technicians', methods: ['GET'])]
    public function technicians(): JsonResponse
    {
        $this->permissions->role($this->api->actor(), Role::Coordinator);

        return new JsonResponse(['items' => $this->directory->technicians()]);
    }
}
