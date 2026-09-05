<?php

declare(strict_types=1);

namespace App\Reporting\Message;

use Symfony\Component\Uid\Uuid;

final readonly class GenerateReport
{
    public function __construct(public string $reportId)
    {
        if (!Uuid::isValid($reportId)) {
            throw new \InvalidArgumentException('Invalid report identifier.');
        }
    }
}
