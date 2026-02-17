<?php
namespace TalerPayments\Settings;

final class SettingsFormMap
{
    public const GROUP_BASEURL = 'taler_baseurl_group';
    public const GROUP_USERPASS = 'taler_userpass_group';
    public const GROUP_TOKEN = 'taler_token_group';

    public static function deleteFlagForOptionPage(string $optionPage): ?string
    {
        return match ($optionPage) {
            self::GROUP_BASEURL => 'taler_baseurl_delete',
            self::GROUP_USERPASS => 'taler_userpass_delete',
            self::GROUP_TOKEN => 'taler_token_delete',
            default => null,
        };
    }
}
