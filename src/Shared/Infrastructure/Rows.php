<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

final class Rows
{
    /** @param array<string,mixed> $row */
    public static function text(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value)) {
            throw new \LogicException('Unexpected database representation.');
        }

        return $value;
    }

    /** @param array<string,mixed> $row */
    public static function integer(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?[0-9]+$/D', $value)) {
            return (int) $value;
        }
        throw new \LogicException('Unexpected numeric representation.');
    }
}
