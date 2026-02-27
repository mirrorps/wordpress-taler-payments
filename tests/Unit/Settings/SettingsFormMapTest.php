<?php

declare(strict_types=1);

namespace TalerPayments\Tests\Unit\Settings;

use PHPUnit\Framework\TestCase;
use TalerPayments\Settings\SettingsFormMap;

final class SettingsFormMapTest extends TestCase
{
    public function testReturnsDeleteFlagForKnownOptionPages(): void
    {
        self::assertSame('taler_baseurl_delete', SettingsFormMap::deleteFlagForOptionPage(SettingsFormMap::GROUP_BASEURL));
        self::assertSame('taler_userpass_delete', SettingsFormMap::deleteFlagForOptionPage(SettingsFormMap::GROUP_USERPASS));
        self::assertSame('taler_token_delete', SettingsFormMap::deleteFlagForOptionPage(SettingsFormMap::GROUP_TOKEN));
        self::assertSame('taler_public_texts_reset', SettingsFormMap::deleteFlagForOptionPage(SettingsFormMap::GROUP_PUBLIC_TEXTS));
    }

    public function testReturnsNullForUnknownOptionPage(): void
    {
        self::assertNull(SettingsFormMap::deleteFlagForOptionPage('unknown_group'));
    }
}
