<?php

declare(strict_types=1);

namespace TalerPayments\Tests\Unit\Settings;

use PHPUnit\Framework\TestCase;
use TalerPayments\Services\MerchantBackendChecker;
use TalerPayments\Services\SettingsNoticesInterface;
use TalerPayments\Services\TalerClientFactoryInterface;
use TalerPayments\Settings\Sanitizer;
use TalerPayments\Settings\SettingsFormMap;
use TalerPayments\Settings\SettingsSaveService;
use TalerPayments\Settings\WordPressSettingsStubState;

final class SettingsSaveServiceTest extends TestCase
{
    protected function setUp(): void
    {
        WordPressSettingsStubState::reset();
    }

    public function testSanitizeForSaveAppliesBaseUrlUpdateForBaseUrlForm(): void
    {
        $service = $this->serviceWithNoNetworkChecker();

        $result = $service->sanitizeForSave(
            ['taler_base_url' => 'https://merchant.example'],
            ['option_page' => SettingsFormMap::GROUP_BASEURL],
            ['keep' => 'value']
        );

        self::assertSame(
            [
                'keep' => 'value',
                'taler_base_url' => 'https://merchant.example',
            ],
            $result
        );
    }

    public function testSanitizeForSaveUsesDeleteFlagFromRequestContext(): void
    {
        $service = $this->serviceWithNoNetworkChecker();

        $result = $service->sanitizeForSave(
            [],
            [
                'option_page' => SettingsFormMap::GROUP_BASEURL,
                'taler_baseurl_delete' => '1',
            ],
            [
                'taler_base_url' => 'https://merchant.example',
                'keep' => 'value',
            ]
        );

        self::assertSame(['keep' => 'value'], $result);
    }

    private function serviceWithNoNetworkChecker(): SettingsSaveService
    {
        $notices = $this->createMock(SettingsNoticesInterface::class);

        $talerFactory = $this->createMock(TalerClientFactoryInterface::class);
        $talerFactory->expects(self::never())->method('createClient');

        $sanitizer = new Sanitizer($notices);
        $checker = new MerchantBackendChecker($notices, null, $talerFactory);

        return new SettingsSaveService($sanitizer, $checker);
    }
}
