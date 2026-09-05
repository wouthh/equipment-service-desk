<?php

declare(strict_types=1);

namespace App\ServiceRequests\Domain;

enum RequestState: string
{
    case Submitted = 'submitted';
    case Triaged = 'triaged';
    case Assigned = 'assigned';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';
}
