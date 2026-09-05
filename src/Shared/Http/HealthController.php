<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Infrastructure\Store;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class HealthController
{
    public function __construct(private Store $store, #[Autowire('%kernel.project_dir%')] private string $projectDir)
    {
    }

    #[Route('/health/live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/health/ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        try {
            foreach (['app_user', 'api_token', 'equipment', 'service_request', 'audit_entry', 'idempotency_record', 'report_job', 'messenger_messages'] as $table) {
                $this->store->em()->getConnection()->executeQuery('SELECT id FROM '.$table.' LIMIT 0');
            }
        } catch (\Throwable) {
            throw new Problem(503, 'not_ready');
        }

        return new JsonResponse(['status' => 'ready']);
    }

    #[Route('/openapi.yaml', methods: ['GET'])]
    public function spec(): Response
    {
        $content = file_get_contents($this->projectDir.'/openapi/openapi.yaml');
        if (false === $content) {
            throw new Problem(503, 'specification_unavailable');
        }

        return new Response($content, 200, ['Content-Type' => 'application/yaml']);
    }
}
