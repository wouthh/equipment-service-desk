<?php

declare(strict_types=1);

namespace App\Equipment\Http;

use App\Access\Application\Permissions;
use App\Access\Domain\Role;
use App\Equipment\Application\Catalog;
use App\Equipment\Application\Input\RegisterEquipmentInput;
use App\Equipment\Application\RegisterEquipment;
use App\Shared\Application\Idempotency;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Input;
use App\Shared\Http\Problem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class EquipmentController
{
    public function __construct(private ApiContext $api, private Input $input, private Permissions $permissions, private Catalog $catalog, private RegisterEquipment $register, private Idempotency $idempotency)
    {
    }

    #[Route('/api/v1/equipment', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->api->actor();
        foreach ($request->query->keys() as $key) {
            if (!in_array($key, ['page', 'limit'], true)) {
                throw new Problem(422, 'unknown_filter');
            }
        }
        $p = $this->input->pagination($request);

        return new JsonResponse(['items' => $this->catalog->page($p['page'], $p['limit']), ...$p]);
    }

    #[Route('/api/v1/equipment/{id}', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $this->api->actor();

        return new JsonResponse($this->catalog->get($id)->view());
    }

    #[Route('/api/v1/equipment', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $actor = $this->api->actor();
        $this->permissions->role($actor, Role::Coordinator);
        $dto = $this->input->read($request, RegisterEquipmentInput::class);

        return $this->idempotency->run($request, $actor, $this->input->payload($dto), function () use ($request, $actor, $dto): JsonResponse {
            $item = $this->register->execute($actor, $dto, $this->api->correlation($request));

            return new JsonResponse($item->view(), 201, ['Location' => '/api/v1/equipment/'.$item->id()]);
        });
    }
}
