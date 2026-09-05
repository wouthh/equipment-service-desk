<?php

declare(strict_types=1);

namespace App\ServiceRequests\Http;

use App\Access\Application\Permissions;
use App\Access\Domain\Role;
use App\ServiceRequests\Application\Input\AssignmentInput;
use App\ServiceRequests\Application\Input\CancellationInput;
use App\ServiceRequests\Application\Input\ResolutionInput;
use App\ServiceRequests\Application\Input\SubmitInput;
use App\ServiceRequests\Application\Input\TriageInput;
use App\ServiceRequests\Application\RequestQueries;
use App\ServiceRequests\Application\SubmitRequest;
use App\ServiceRequests\Application\TransitionRequest;
use App\ServiceRequests\Domain\RequestState;
use App\Shared\Application\Idempotency;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Input;
use App\Shared\Http\Problem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final readonly class RequestController
{
    public function __construct(private ApiContext $api, private Input $input, private Permissions $permissions, private RequestQueries $queries, private SubmitRequest $submit, private TransitionRequest $transition, private Idempotency $idempotency)
    {
    }

    #[Route('/api/v1/service-requests', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $actor = $this->api->actor();
        foreach ($request->query->keys() as $key) {
            if (!in_array($key, ['page', 'limit', 'state'], true)) {
                throw new Problem(422, 'unknown_filter');
            }
        }
        $state = $request->query->get('state');
        if (null !== $state && null === RequestState::tryFrom($state)) {
            throw new Problem(422, 'invalid_state');
        }
        $p = $this->input->pagination($request);

        return new JsonResponse(['items' => $this->queries->page($actor, $p['page'], $p['limit'], $state), ...$p]);
    }

    #[Route('/api/v1/service-requests/{id}', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $item = $this->queries->visible($id, $this->api->actor());

        return new JsonResponse($item->view(), 200, ['ETag' => $item->etag()]);
    }

    #[Route('/api/v1/service-requests/{id}/history', methods: ['GET'])]
    public function history(string $id, Request $request): JsonResponse
    {
        foreach ($request->query->keys() as $key) {
            if (!in_array($key, ['page', 'limit'], true)) {
                throw new Problem(422, 'unknown_filter');
            }
        }
        $item = $this->queries->visible($id, $this->api->actor());
        $p = $this->input->pagination($request);

        return new JsonResponse(['items' => $this->queries->history($item, $p['page'], $p['limit']), ...$p]);
    }

    #[Route('/api/v1/service-requests', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $actor = $this->api->actor();
        $this->permissions->role($actor, Role::Requester);
        $dto = $this->input->read($request, SubmitInput::class);

        return $this->idempotency->run($request, $actor, $this->input->payload($dto), function () use ($actor, $dto, $request): JsonResponse {
            $item = $this->submit->execute($actor, $dto, $this->api->correlation($request));

            return new JsonResponse($item->view(), 201, ['Location' => '/api/v1/service-requests/'.$item->id(), 'ETag' => $item->etag()]);
        });
    }

    #[Route('/api/v1/service-requests/{id}/{action}', requirements: ['action' => 'triage|assignment|resolution|cancellation'], methods: ['POST'])]
    public function change(string $id, string $action, Request $request): JsonResponse
    {
        $actor = $this->api->actor();
        $item = $this->queries->visible($id, $actor);
        $this->permissions->transition($actor, $item, $action);
        $type = match ($action) {
            'triage' => TriageInput::class,'assignment' => AssignmentInput::class,'resolution' => ResolutionInput::class,default => CancellationInput::class,
        };
        $dto = $this->input->read($request, $type);
        $etag = $this->input->etag($request);

        return $this->idempotency->run($request, $actor, $this->input->payload($dto), function () use ($actor, $item, $action, $dto, $etag, $request): JsonResponse {
            $changed = $this->transition->execute($actor, $item, $action, $dto, $etag, $this->api->correlation($request));

            return new JsonResponse($changed->view(), 200, ['ETag' => $changed->etag()]);
        });
    }
}
