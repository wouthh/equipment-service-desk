<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Infrastructure\Ids;

final class SequenceIds implements Ids
{
    private int $next = 1;

    public function next(): string
    {
        return sprintf('00000000-0000-7000-8000-%012d', $this->next++);
    }
}
