<?php

declare(strict_types=1);

namespace App\Shared\Http;

final class Problem extends \RuntimeException
{
    /** @param list<array{field:string,code:string}> $violations */
    public function __construct(public readonly int $status, public readonly string $problemCode, public readonly array $violations = [])
    {
        parent::__construct($problemCode);
    }
}
