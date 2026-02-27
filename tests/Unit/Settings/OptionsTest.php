<?php

declare(strict_types=1);

namespace TalerPayments\Tests\Unit\Settings;

use PHPUnit\Framework\TestCase;
use TalerPayments\Settings\Options;
use TalerPayments\Settings\WordPressSettingsStubState;

final class OptionsTest extends TestCase
{
    protected function setUp(): void
    {
        WordPressSettingsStubState::reset();
    }

    public function testGetReturnsStoredArrayForDefaultOptionName(): void
    {
        WordPressSettingsStubState::$optionsByName[Options::OPTION_NAME] = ['k' => 'v'];

        self::assertSame(['k' => 'v'], Options::get());
    }

    public function testGetReturnsEmptyArrayWhenStoredValueIsNotArray(): void
    {
        WordPressSettingsStubState::$optionsByName[Options::OPTION_NAME] = 'invalid';

        self::assertSame([], Options::get());
    }

    public function testGetSupportsCustomOptionName(): void
    {
        WordPressSettingsStubState::$optionsByName['custom_option'] = ['x' => 1];

        self::assertSame(['x' => 1], Options::get('custom_option'));
    }
}
