<?php

declare(strict_types=1);

namespace TalerPayments\Settings;

final class WordPressSettingsStubState
{
    public static bool $canManageOptions = true;

    /** @var array<string,mixed> */
    public static array $optionsByName = [];

    public static function reset(): void
    {
        self::$canManageOptions = true;
        self::$optionsByName = [];
    }
}

if (!function_exists(__NAMESPACE__ . '\\current_user_can')) {
    function current_user_can($capability, ...$args): bool
    {
        return WordPressSettingsStubState::$canManageOptions;
    }
}

if (!function_exists(__NAMESPACE__ . '\\esc_url_raw')) {
    /**
     * @param list<string> $protocols
     */
    function esc_url_raw(string $url, array $protocols = []): string
    {
        return trim($url);
    }
}

if (!function_exists(__NAMESPACE__ . '\\wp_parse_url')) {
    function wp_parse_url($url, $component = -1): array|false
    {
        return parse_url($url);
    }
}

if (!function_exists(__NAMESPACE__ . '\\get_option')) {
    function get_option(string $option): mixed
    {
        return WordPressSettingsStubState::$optionsByName[$option] ?? null;
    }
}
