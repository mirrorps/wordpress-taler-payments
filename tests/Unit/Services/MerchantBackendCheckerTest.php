<?php

declare(strict_types=1);

namespace TalerPayments\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use TalerPayments\Services\MerchantAuthConfigurator;
use TalerPayments\Services\MerchantBackendChecker;
use TalerPayments\Services\SettingsNoticesInterface;
use TalerPayments\Services\TalerClientFactoryInterface;

final class MerchantBackendCheckerTest extends TestCase
{
    public function testLoginReturnsTrueWithoutConfiguredCredentials(): void
    {
        $notices = $this->createMock(SettingsNoticesInterface::class);
        $notices->expects(self::never())->method('addOnce');

        $talerFactory = $this->createMock(TalerClientFactoryInterface::class);
        $talerFactory->expects(self::never())->method('createClient');

        $checker = new MerchantBackendChecker(
            $notices,
            new MerchantAuthConfigurator(),
            $talerFactory
        );

        self::assertTrue($checker->testLogin([]));
    }

    public function testLoginAddsSuccessNoticeWhenConfigCheckIsOk(): void
    {
        $notices = $this->createMock(SettingsNoticesInterface::class);
        $notices
            ->expects(self::once())
            ->method('addOnce')
            ->with(
                'taler_options',
                'taler_backend_login_ok',
                self::stringContains('successful'),
                'updated'
            );

        $client = $this->getMockBuilder(\Taler\Taler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['configCheck'])
            ->getMock();
        $client->method('configCheck')->willReturn(['ok' => true]);

        $talerFactory = $this->createMock(TalerClientFactoryInterface::class);
        $talerFactory->method('createClient')->willReturn($client);

        $checker = new MerchantBackendChecker(
            $notices,
            $this->configuratorWithDecryptMap(['enc-token' => 'abc123']),
            $talerFactory
        );

        self::assertTrue($checker->testLogin($this->tokenOptions()));
    }

    public function testLoginAddsFailureNoticeForFailedConfigStep(): void
    {
        $notices = $this->createMock(SettingsNoticesInterface::class);
        $notices
            ->expects(self::once())
            ->method('addOnce')
            ->with(
                'taler_options',
                'taler_backend_login_failed',
                self::logicalAnd(
                    self::stringContains('config'),
                    self::stringContains('HTTP 401'),
                    self::stringContains('invalid-token')
                ),
                'error'
            );

        $client = $this->getMockBuilder(\Taler\Taler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['configCheck'])
            ->getMock();
        $client->method('configCheck')->willReturn([
            'ok' => false,
            'config' => [
                'ok' => false,
                'status' => 401,
                'error' => 'invalid-token',
            ],
        ]);

        $talerFactory = $this->createMock(TalerClientFactoryInterface::class);
        $talerFactory->method('createClient')->willReturn($client);

        $checker = new MerchantBackendChecker(
            $notices,
            $this->configuratorWithDecryptMap(['enc-token' => 'abc123']),
            $talerFactory
        );

        self::assertFalse($checker->testLogin($this->tokenOptions()));
    }

    public function testLoginAddsInvalidConfigurationNoticeForInvalidArgumentException(): void
    {
        $notices = $this->createMock(SettingsNoticesInterface::class);
        $notices
            ->expects(self::once())
            ->method('addOnce')
            ->with(
                'taler_options',
                'taler_backend_login_invalid',
                self::stringContains('invalid configuration'),
                'error'
            );

        $talerFactory = $this->createMock(TalerClientFactoryInterface::class);
        $talerFactory
            ->method('createClient')
            ->willThrowException(new \InvalidArgumentException('bad base url'));

        $checker = new MerchantBackendChecker(
            $notices,
            $this->configuratorWithDecryptMap(['enc-token' => 'abc123']),
            $talerFactory
        );

        self::assertFalse($checker->testLogin($this->tokenOptions()));
    }

    public function testLoginAddsGenericNoticeForUnexpectedThrowable(): void
    {
        $notices = $this->createMock(SettingsNoticesInterface::class);
        $notices
            ->expects(self::once())
            ->method('addOnce')
            ->with(
                'taler_options',
                'taler_backend_login_exception',
                self::logicalAnd(
                    self::stringContains('Access Token'),
                    self::stringContains('access token')
                ),
                'error'
            );

        $client = $this->getMockBuilder(\Taler\Taler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['configCheck'])
            ->getMock();
        $client->method('configCheck')->willThrowException(new \RuntimeException('boom'));

        $talerFactory = $this->createMock(TalerClientFactoryInterface::class);
        $talerFactory->method('createClient')->willReturn($client);

        $checker = new MerchantBackendChecker(
            $notices,
            $this->configuratorWithDecryptMap(['enc-token' => 'abc123']),
            $talerFactory
        );

        self::assertFalse($checker->testLogin($this->tokenOptions()));
    }

    public function testLoginSkipsDuplicateCheckForSameInputAndMode(): void
    {
        $notices = $this->createMock(SettingsNoticesInterface::class);
        $notices->expects(self::once())->method('addOnce');

        $client = $this->getMockBuilder(\Taler\Taler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['configCheck'])
            ->getMock();
        $client->method('configCheck')->willReturn(['ok' => true]);

        $talerFactory = $this->createMock(TalerClientFactoryInterface::class);
        $talerFactory
            ->expects(self::once())
            ->method('createClient')
            ->willReturn($client);

        $checker = new MerchantBackendChecker(
            $notices,
            $this->configuratorWithDecryptMap(['enc-token' => 'abc123']),
            $talerFactory
        );

        self::assertTrue($checker->testLogin($this->tokenOptions(), MerchantAuthConfigurator::MODE_AUTO));
        self::assertTrue($checker->testLogin($this->tokenOptions(), MerchantAuthConfigurator::MODE_AUTO));
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

    /**
     * @return array<string,string>
     */
    private function tokenOptions(): array
    {
        return [
            'taler_base_url' => 'https://merchant.example',
            'taler_token' => 'enc-token',
        ];
    }
}
