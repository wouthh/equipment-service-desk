<?php

declare(strict_types=1);

namespace App\Access\Domain;

enum Role: string
{
    case Requester = 'requester';
    case Coordinator = 'coordinator';
    case Technician = 'technician';
}
