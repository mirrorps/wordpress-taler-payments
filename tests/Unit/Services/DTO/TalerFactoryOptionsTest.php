<?php

declare(strict_types=1);

namespace TalerPayments\Tests\Unit\Services\DTO;

use PHPUnit\Framework\TestCase;
use TalerPayments\Services\DTO\TalerFactoryOptions;

final class TalerFactoryOptionsTest extends TestCase
{
    public function testWithBaseUrlCreatesMinimalOptionsArray(): void
    {
        $options = TalerFactoryOptions::withBaseUrl('https://merchant.example');

        self::assertSame(
            ['base_url' => 'https://merchant.example'],
            $options->toArray()
        );
    }

    public function testFromArrayNormalizesScalarValues(): void
    {
        $options = TalerFactoryOptions::fromArray([
            'base_url' => 123,
            'token' => 999,
            'username' => 'alice',
            'password' => 'secret',
            'instance' => 'default',
            'scope' => 'write',
            'duration_us' => '60000000',
            'description' => false,
        ]);

        self::assertSame(
            [
                'base_url' => '123',
                'token' => '999',
                'username' => 'alice',
                'password' => 'secret',
                'instance' => 'default',
                'scope' => 'write',
                'duration_us' => 60000000,
                'description' => '',
            ],
            $options->toArray()
        );
    }

    public function testWithTokenReturnsNewInstanceAndKeepsOriginalUntouched(): void
    {
        $original = TalerFactoryOptions::withBaseUrl('https://merchant.example');
        $updated = $original->withToken('tok_abc');

        self::assertSame(['base_url' => 'https://merchant.example'], $original->toArray());
        self::assertSame(
            [
                'base_url' => 'https://merchant.example',
                'token' => 'tok_abc',
            ],
            $updated->toArray()
        );
    }

    public function testWithUserPassMergesCredentialsWhileKeepingExistingToken(): void
    {
        $options = TalerFactoryOptions::withBaseUrl('https://merchant.example')
            ->withToken('tok_abc')
            ->withUserPass(
                username: 'merchant-user',
                password: 'merchant-pass',
                instance: 'default',
                scope: 'write',
                durationUs: 120000000,
                description: 'Runtime login',
            );

        self::assertSame(
            [
                'base_url' => 'https://merchant.example',
                'token' => 'tok_abc',
                'username' => 'merchant-user',
                'password' => 'merchant-pass',
                'instance' => 'default',
                'scope' => 'write',
                'duration_us' => 120000000,
                'description' => 'Runtime login',
            ],
            $options->toArray()
        );
    }
}
