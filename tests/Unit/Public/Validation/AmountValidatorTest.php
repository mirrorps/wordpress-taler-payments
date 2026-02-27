<?php

declare(strict_types=1);

namespace TalerPayments\Tests\Unit\Public\Validation;

use PHPUnit\Framework\TestCase;
use TalerPayments\Public\Validation\AmountValidator;

final class AmountValidatorTest extends TestCase
{
    public function testAcceptsValidAmountFormat(): void
    {
        $validator = new AmountValidator();

        self::assertSame('KUDOS:1.25', $validator->validateAmount('KUDOS:1.25'));
    }

    public function testPreservesOriginalNumericPrecision(): void
    {
        $validator = new AmountValidator();

        self::assertSame('KUDOS:1.2300', $validator->validateAmount('KUDOS:1.2300'));
    }

    public function testSanitizesInputBeforeValidation(): void
    {
        $validator = new AmountValidator();

        self::assertSame('KUDOS:2.50', $validator->validateAmount('<b>KUDOS:2.50</b>'));
    }

    public function testRejectsInvalidCurrencyFormat(): void
    {
        $validator = new AmountValidator();

        self::assertNull($validator->validateAmount('kudos:1.25'));
    }

    public function testRejectsAmountBelowMinimum(): void
    {
        $validator = new AmountValidator();

        self::assertNull($validator->validateAmount('KUDOS:0.001'));
    }

    public function testRejectsAmountAboveMaximum(): void
    {
        $validator = new AmountValidator();

        self::assertNull($validator->validateAmount('KUDOS:1000000.01'));
    }

    public function testRejectsInvalidAmountFormat(): void
    {
        $validator = new AmountValidator();

        self::assertNull($validator->validateAmount('invalid'));
    }
}
