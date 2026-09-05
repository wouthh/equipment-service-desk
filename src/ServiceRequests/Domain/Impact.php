<?php

declare(strict_types=1);

namespace App\ServiceRequests\Domain;

enum Impact: string
{
    case Stopped = 'stopped';
    case Degraded = 'degraded';
}
