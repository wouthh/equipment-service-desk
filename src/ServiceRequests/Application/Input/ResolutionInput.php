<?php

declare(strict_types=1);

namespace App\ServiceRequests\Application\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ResolutionInput
{
    public function __construct(#[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 2000)] public string $summary = '')
    {
    }
}
