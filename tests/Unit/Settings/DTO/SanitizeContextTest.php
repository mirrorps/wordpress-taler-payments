<?php

declare(strict_types=1);

namespace TalerPayments\Tests\Unit\Settings\DTO;

use PHPUnit\Framework\TestCase;
use TalerPayments\Settings\DTO\SanitizeContext;

final class SanitizeContextTest extends TestCase
{
    public function testExposesOptionPageAndDeleteFlag(): void
    {
        $context = new SanitizeContext('taler_baseurl_group', true);

        self::assertSame('taler_baseurl_group', $context->optionPage());
        self::assertTrue($context->isDelete());
    }
}
