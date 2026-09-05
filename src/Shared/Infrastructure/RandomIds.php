<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use Symfony\Component\Uid\Uuid;

final class RandomIds implements Ids
{
    public function next(): string
    {
        return Uuid::v7()->toRfc4122();
    }
}
