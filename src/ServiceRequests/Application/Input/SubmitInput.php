<?php

declare(strict_types=1);

namespace App\ServiceRequests\Application\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SubmitInput
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Uuid] public string $equipmentId = '',
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 120)] public string $title = '',
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 4000)] public string $description = '',
        #[Assert\Choice(['stopped', 'degraded'])] public string $reportedImpact = '',
    ) {
    }
}
