<?php
namespace TalerPayments\Public\Config;

/**
 * Option keys and fallback values for customizable public UI text.
 */
final class PublicUiTexts
{
    public const OPTION_THANK_YOU_MESSAGE = 'public_thank_you_message';
    public const OPTION_PAY_BUTTON_CTA = 'public_pay_button_cta';
    public const OPTION_CHECK_STATUS_BUTTON = 'public_check_status_button_text';
    public const OPTION_CHECK_STATUS_HINT = 'public_check_status_hint';

    public const DEFAULT_THANK_YOU_MESSAGE = 'Payment received. Thank you!';
    public const DEFAULT_PAY_BUTTON_CTA = 'Pay with Taler wallet in the browser';
    public const DEFAULT_CHECK_STATUS_BUTTON = 'Check payment status';
    public const DEFAULT_CHECK_STATUS_HINT = 'After you finish the payment in your wallet, click this button to refresh payment status text';

    /**
     * @param array<string, mixed> $options
     * @return array<string, string>
     */
    public static function resolve(array $options): array
    {
        return [
            self::OPTION_THANK_YOU_MESSAGE => self::optionValueOrDefault($options, self::OPTION_THANK_YOU_MESSAGE, self::DEFAULT_THANK_YOU_MESSAGE),
            self::OPTION_PAY_BUTTON_CTA => self::optionValueOrDefault($options, self::OPTION_PAY_BUTTON_CTA, self::DEFAULT_PAY_BUTTON_CTA),
            self::OPTION_CHECK_STATUS_BUTTON => self::optionValueOrDefault($options, self::OPTION_CHECK_STATUS_BUTTON, self::DEFAULT_CHECK_STATUS_BUTTON),
            self::OPTION_CHECK_STATUS_HINT => self::optionValueOrDefault($options, self::OPTION_CHECK_STATUS_HINT, self::DEFAULT_CHECK_STATUS_HINT),
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function optionValueOrDefault(array $options, string $key, string $default): string
    {
        if (!array_key_exists($key, $options)) {
            return $default;
        }

        $value = trim((string) $options[$key]);
        return $value !== '' ? $value : $default;
    }
}
