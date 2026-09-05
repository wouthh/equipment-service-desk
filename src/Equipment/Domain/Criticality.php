<?php

declare(strict_types=1);

namespace App\Equipment\Domain;

enum Criticality: string
{
    case Normal = 'normal';
    case Critical = 'critical';
}
