<?php

declare(strict_types=1);

namespace App\Reporting\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReportInput
{
    public function __construct(#[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 25)] public string $from = '', #[Assert\NotBlank(normalizer: 'trim'), Assert\Length(max: 25)] public string $to = '')
    {
    }
}
