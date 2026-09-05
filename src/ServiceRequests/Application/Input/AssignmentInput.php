<?php

declare(strict_types=1);

namespace App\ServiceRequests\Application\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AssignmentInput
{
    public function __construct(#[Assert\NotBlank(normalizer: 'trim'), Assert\Uuid] public string $technicianId = '')
    {
    }
}
