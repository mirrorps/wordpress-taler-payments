<?php

declare(strict_types=1);

namespace TalerPayments\Tests\Unit\Settings;

use PHPUnit\Framework\TestCase;
use TalerPayments\Services\MerchantAuthConfigurator;
use TalerPayments\Services\SettingsNoticesInterface;
use TalerPayments\Settings\DTO\SanitizeContext;
use TalerPayments\Settings\Sanitizer;
use TalerPayments\Settings\SettingsFormMap;
use TalerPayments\Settings\WordPressSettingsStubState;

final class SanitizerTest extends TestCase
{
    protected function setUp(): void
    {
        WordPressSettingsStubState::reset();
    }

    public function testReturnsOldOptionsWhenUserHasNoPermission(): void
    {
        WordPressSettingsStubState::$canManageOptions = false;

        $notices = $this->createMock(SettingsNoticesInterface::class);
        $notices->expects(self::once())->method('addOnce');

        $sanitizer = new Sanitizer($notices);
        $old = ['existing' => 'value'];

        $result = $sanitizer->sanitize(
            ['taler_base_url' => 'https://merchant.example'],
            new SanitizeContext(SettingsFormMap::GROUP_BASEURL, false),
            $old
        );

        self::assertSame($old, $result->options());
        self::assertNull($result->loginCheckMode());
    }

    public function testSanitizeBaseUrlGroupAcceptsHttpsAndTriggersAutoLoginCheck(): void
    {
        $notices = $this->createMock(SettingsNoticesInterface::class);
        $notices->expects(self::never())->method('addOnce');

        $sanitizer = new Sanitizer($notices);
        $result = $sanitizer->sanitize(
            ['taler_base_url' => ' https://merchant.example '],
            new SanitizeContext(SettingsFormMap::GROUP_BASEURL, false),
            ['foo' => 'bar']
        );

        self::assertSame(
            [
                'foo' => 'bar',
                'taler_base_url' => 'https://merchant.example',
            ],
            $result->options()
        );
        self::assertSame(MerchantAuthConfigurator::MODE_AUTO, $result->loginCheckMode());
    }

    public function testSanitizeBaseUrlGroupRejectsNonHttpsUrl(): void
    {
        $notices = $this->createMock(SettingsNoticesInterface::class);
        $notices
            ->expects(self::once())
            ->method('addOnce')
            ->with(
                'taler_options',
                'taler_baseurl_invalid',
                self::stringContains('https://'),
                'error'
            );

        $sanitizer = new Sanitizer($notices);
        $old = ['existing' => 'value'];

        $result = $sanitizer->sanitize(
            ['taler_base_url' => 'http://merchant.example'],
            new SanitizeContext(SettingsFormMap::GROUP_BASEURL, false),
            $old
        );

        self::assertSame($old, $result->options());
        self::assertNull($result->loginCheckMode());
    }

    public function testSanitizePublicTextsDeleteResetsConfiguredKeys(): void
    {
        $notices = $this->createMock(SettingsNoticesInterface::class);
        $notices->expects(self::once())->method('addOnce');

        $sanitizer = new Sanitizer($notices);
        $old = [
            'public_thank_you_message' => 'Thanks!',
            'public_pay_button_cta' => 'Pay now',
            'keep' => 'yes',
        ];

        $result = $sanitizer->sanitize(
            [],
            new SanitizeContext(SettingsFormMap::GROUP_PUBLIC_TEXTS, true),
            $old
        );

        self::assertSame(['keep' => 'yes'], $result->options());
        self::assertNull($result->loginCheckMode());
    }
}
