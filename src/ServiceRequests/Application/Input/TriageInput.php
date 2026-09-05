<?php

declare(strict_types=1);

namespace App\ServiceRequests\Application\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TriageInput
{
    public function __construct(#[Assert\Choice(['stopped', 'degraded'])] public string $impact = '')
    {
    }
}
