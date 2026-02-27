<?php

declare(strict_types=1);

namespace TalerPayments\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use TalerPayments\Services\MerchantAuthConfigurator;

final class MerchantAuthConfiguratorTest extends TestCase
{
    public function testNormalizeAuthTokenAddsBearerPrefix(): void
    {
        $configurator = new MerchantAuthConfigurator();

        self::assertSame('Bearer abc123', $configurator->normalizeAuthToken('abc123'));
    }

    public function testNormalizeAuthTokenKeepsBearerAndBasicTokens(): void
    {
        $configurator = new MerchantAuthConfigurator();

        self::assertSame('Bearer xyz', $configurator->normalizeAuthToken('Bearer xyz'));
        self::assertSame('Basic dXNlcjpwYXNz', $configurator->normalizeAuthToken('Basic dXNlcjpwYXNz'));
    }

    public function testBuildClientFactoryOptionsUsesTokenWhenBothTokenAndUserpassExist(): void
    {
        $configurator = $this->configuratorWithDecryptMap([
            'enc-token' => 'merchant-token',
            'enc-pass' => 'merchant-pass',
        ]);

        $options = $configurator->buildClientFactoryOptions([
            'taler_base_url' => 'https://merchant.example',
            'taler_token' => 'enc-token',
            'ext_username' => 'merchant-user',
            'ext_password' => 'enc-pass',
            'taler_instance' => 'default',
        ]);

        self::assertSame(
            [
                'base_url' => 'https://merchant.example',
                'token' => 'Bearer merchant-token',
            ],
            $options->toArray()
        );
    }

    public function testBuildClientFactoryOptionsUsesRuntimeUserpassWhenTokenMissing(): void
    {
        $configurator = $this->configuratorWithDecryptMap([
            'enc-pass' => 'merchant-pass',
        ]);

        $options = $configurator->buildClientFactoryOptions([
            'taler_base_url' => 'https://merchant.example',
            'ext_username' => 'merchant-user',
            'ext_password' => 'enc-pass',
            'taler_instance' => 'default',
        ]);

        self::assertSame(
            [
                'base_url' => 'https://merchant.example',
                'username' => 'merchant-user',
                'password' => 'merchant-pass',
                'instance' => 'default',
                'scope' => 'order-full',
                'duration_us' => 3600000000,
                'description' => 'WordPress taler-payments',
            ],
            $options->toArray()
        );
    }

    public function testBuildRuntimeUserPassFactoryOptionsReturnsNullWhenCredentialsIncomplete(): void
    {
        $configurator = new MerchantAuthConfigurator();

        self::assertNull($configurator->buildRuntimeUserPassFactoryOptions([
            'taler_base_url' => 'https://merchant.example',
            'ext_username' => 'merchant-user',
            // missing password and instance
        ]));
    }

    public function testBuildLoginCheckContextTokenModeReturnsTokenContext(): void
    {
        $configurator = $this->configuratorWithDecryptMap([
            'enc-token' => 'merchant-token',
        ]);

        $context = $configurator->buildLoginCheckContext([
            'taler_base_url' => 'https://merchant.example',
            'taler_token' => 'enc-token',
        ], MerchantAuthConfigurator::MODE_TOKEN);

        self::assertNotNull($context);
        self::assertSame(MerchantAuthConfigurator::AUTH_METHOD_TOKEN, $context->authMethod);
        self::assertSame(
            [
                'base_url' => 'https://merchant.example',
                'token' => 'Bearer merchant-token',
            ],
            $context->factoryOptions->toArray()
        );
    }

    public function testBuildLoginCheckContextUserpassModeReturnsReadonlyScope(): void
    {
        $configurator = $this->configuratorWithDecryptMap([
            'enc-pass' => 'merchant-pass',
        ]);

        $context = $configurator->buildLoginCheckContext([
            'taler_base_url' => 'https://merchant.example',
            'ext_username' => 'merchant-user',
            'ext_password' => 'enc-pass',
            'taler_instance' => 'default',
        ], MerchantAuthConfigurator::MODE_USERPASS);

        self::assertNotNull($context);
        self::assertSame(MerchantAuthConfigurator::AUTH_METHOD_USERPASS, $context->authMethod);
        self::assertSame(
            [
                'base_url' => 'https://merchant.example',
                'username' => 'merchant-user',
                'password' => 'merchant-pass',
                'instance' => 'default',
                'scope' => 'readonly',
                'duration_us' => 3600000000,
                'description' => 'WordPress taler-payments settings check',
            ],
            $context->factoryOptions->toArray()
        );
    }

    public function testBuildLoginCheckContextReturnsNullForInvalidMode(): void
    {
        $configurator = new MerchantAuthConfigurator();

        self::assertNull($configurator->buildLoginCheckContext(
            ['taler_base_url' => 'https://merchant.example'],
            'invalid-mode'
        ));
    }

    /**
     * @param array<string,string> $decryptMap
     */
    private function configuratorWithDecryptMap(array $decryptMap): MerchantAuthConfigurator
    {
        return new MerchantAuthConfigurator(
            static fn (string $encrypted): string => $decryptMap[$encrypted] ?? ''
        );
    }
}
