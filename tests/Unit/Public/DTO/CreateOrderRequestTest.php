<?php

declare(strict_types=1);

namespace TalerPayments\Tests\Unit\Public\DTO;

use PHPUnit\Framework\TestCase;
use TalerPayments\Public\Config\PublicDefaults;
use TalerPayments\Public\DTO\CreateOrderRequest;
use TalerPayments\Public\DTO\InvalidAmountException;
use TalerPayments\Public\Input\ArrayInput;
use TalerPayments\Public\Input\InputInterface;
use TalerPayments\Public\Validation\AmountValidatorInterface;

final class CreateOrderRequestTest extends TestCase
{
    public function testCreatesRequestFromInput(): void
    {
        $input = $this->input([
            'amount' => 'KUDOS:3.99',
            'summary' => 'Support',
        ]);
        $validator = $this->validatorReturning('KUDOS:3.99');

        $request = CreateOrderRequest::fromInput($input, $validator);

        self::assertSame('KUDOS:3.99', $request->amount());
        self::assertSame('Support', $request->summary());
    }

    public function testThrowsForInvalidAmount(): void
    {
        $input = $this->input([
            'amount' => 'bad',
            'summary' => PublicDefaults::SUMMARY,
        ]);
        $validator = $this->validatorReturning(null);

        $this->expectException(InvalidAmountException::class);

        CreateOrderRequest::fromInput($input, $validator);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function input(array $payload): InputInterface
    {
        return new ArrayInput($payload);
    }

    private function validatorReturning(?string $validatedAmount): AmountValidatorInterface
    {
        $validator = $this->createStub(AmountValidatorInterface::class);
        $validator
            ->method('validateAmount')
            ->willReturn($validatedAmount);

        return $validator;
    }
}
