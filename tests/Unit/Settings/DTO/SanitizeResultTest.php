<?php

declare(strict_types=1);

namespace TalerPayments\Tests\Unit\Settings\DTO;

use PHPUnit\Framework\TestCase;
use TalerPayments\Settings\DTO\SanitizeResult;

final class SanitizeResultTest extends TestCase
{
    public function testWithLoginCheckStoresOptionsAndMode(): void
    {
        $result = SanitizeResult::withLoginCheck(['a' => 1], 'token');

        self::assertSame(['a' => 1], $result->options());
        self::assertSame('token', $result->loginCheckMode());
    }

    public function testWithoutLoginCheckStoresNullMode(): void
    {
        $result = SanitizeResult::withoutLoginCheck(['b' => 2]);

        self::assertSame(['b' => 2], $result->options());
        self::assertNull($result->loginCheckMode());
    }
}
