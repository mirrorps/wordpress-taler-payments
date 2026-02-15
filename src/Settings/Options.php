<?php
namespace TalerPayments\Settings;

/**
 * Access to the plugin option storage.
 */
final class Options
{
    public const OPTION_NAME = 'taler_options';

    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        $options = get_option(self::OPTION_NAME);
        return is_array($options) ? $options : [];
    }
}

