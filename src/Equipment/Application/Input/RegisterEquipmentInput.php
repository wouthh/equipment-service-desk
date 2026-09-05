<?php

declare(strict_types=1);

namespace App\Equipment\Application\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterEquipmentInput
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Regex('/^[A-Z][A-Z0-9-]{1,31}$/D')] public string $assetTag = '',
        #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 100)] public string $name = '',
        #[Assert\Choice(['normal', 'critical'])] public string $criticality = '',
    ) {
    }
}
