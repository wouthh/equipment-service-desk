<?php

declare(strict_types=1);

namespace App\ServiceRequests\Application\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CancellationInput
{
    public function __construct(#[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 500)] public string $reason = '')
    {
    }
}
