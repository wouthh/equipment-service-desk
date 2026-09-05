<?php

declare(strict_types=1);

namespace App\Reporting\Http;

use App\Access\Application\Permissions;
use App\Access\Domain\Role;
use App\Reporting\Application\ReportQueries;
use App\Reporting\Application\RequestReport;
use App\Reporting\Domain\ReportCriteria;
use App\Shared\Application\Idempotency;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Input;
use App\Shared\Http\Limits;
use App\Shared\Http\Problem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ReportController
{
    public function __construct(private ApiContext $api, private Input $input, private Permissions $permissions, private RequestReport $create, private ReportQueries $queries, private Idempotency $idempotency, private Limits $limits)
    {
    }

    #[Route('/api/v1/reports', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $actor = $this->api->actor();
        $this->permissions->role($actor, Role::Coordinator);
        $dto = $this->input->read($request, ReportInput::class);
        try {
            $criteria = ReportCriteria::parse($dto->from, $dto->to);
        } catch (\InvalidArgumentException) {
            throw new Problem(422, 'invalid_report_interval');
        }

        return $this->idempotency->run($request, $actor, $this->input->payload($dto), function () use ($request, $actor, $criteria): JsonResponse {
            $this->limits->report($actor);
            $job = $this->create->execute($actor, $criteria, $this->api->correlation($request));

            return new JsonResponse($job->view(), 202, ['Location' => '/api/v1/reports/'.$job->id()]);
        });
    }

    #[Route('/api/v1/reports/{id}', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        return new JsonResponse($this->queries->get($id, $this->api->actor())->view());
    }

    #[Route('/api/v1/reports/{id}/download', methods: ['GET'])]
    public function download(string $id): Response
    {
        $job = $this->queries->get($id, $this->api->actor());
        if ('ready' !== $job->status() || null === $job->csv()) {
            throw new Problem(409, 'report_not_ready');
        }

        return new Response($job->csv(), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="equipment-service-report.csv"']);
    }
}
