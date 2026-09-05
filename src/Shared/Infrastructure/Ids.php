<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

interface Ids
{
    public function next(): string;
}
