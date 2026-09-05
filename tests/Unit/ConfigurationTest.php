<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Shared\Infrastructure\ConfigurationGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    /** @return iterable<array{string,string}> */
    public static function invalid(): iterable
    {
        foreach (['', ' ', 'replace-with-generated-local-secret'] as $value) {
            yield [$value, 'postgresql://desk_runtime:'.str_repeat('a', 64).'@db/desk'];
        }
        foreach (['', 'sqlite://local', 'postgresql://desk_runtime:example@db/desk', 'postgresql://desk_runtime:'.str_repeat('a', 64).'@example.invalid/desk', 'postgresql://desk_runtime:'.str_repeat('a', 64).'@db/desk?host=example.invalid'] as $url) {
            yield [str_repeat('a', 64), $url];
        }
    }

    #[DataProvider('invalid')]
    public function testMissingOrExampleConfigurationFailsClosed(string $secret, string $url): void
    {
        $this->expectException(\RuntimeException::class);
        (new ConfigurationGuard($secret, $url))->validate();
    }
}
