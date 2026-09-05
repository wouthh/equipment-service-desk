<?php

declare(strict_types=1);

namespace App\ServiceRequests\Domain;

enum Priority: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
}
