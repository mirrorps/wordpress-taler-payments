<?php
namespace TalerPayments\Bootstrap;

use TalerPayments\Admin\SettingsPage;
use TalerPayments\Services\MerchantBackendChecker;
use TalerPayments\Services\SettingsNotices;
use TalerPayments\Settings\Sanitizer;
use TalerPayments\Settings\SettingsSaveService;

/**
 * Registers admin-only plugin wiring.
 */
final class AdminBootstrap
{
    public function boot(): void
    {
        if (!is_admin()) {
            return;
        }

        $notices = new SettingsNotices();
        $checker = new MerchantBackendChecker($notices);
        $sanitizer = new Sanitizer($notices);
        $settingsSaveService = new SettingsSaveService($sanitizer, $checker);
        $settingsPage = new SettingsPage($settingsSaveService);
        $settingsPage->hooks();
    }
}
