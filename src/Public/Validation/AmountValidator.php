<?php
namespace TalerPayments\Public\Validation;

/**
 * Validates and normalizes Taler amount strings.
 */
final class AmountValidator implements AmountValidatorInterface
{
    /**
     * Validate and normalize amount format: CURRENCY:12.34
     *
     * Returns normalized amount string or null when invalid.
     */
    public function validateAmount(string $raw): ?string
    {
        $value = sanitize_text_field($raw);

        // Allow up to 8 fractional digits to avoid rejecting valid merchant amount formats.
        if (!preg_match('/^([A-Z][A-Z0-9_-]{1,11}):([0-9]+(?:\.[0-9]{1,8})?)$/', $value, $matches)) {
            return null;
        }

        $currency = $matches[1];
        $number = (float) $matches[2];

        // Keep sane business bounds; configurable for sites with different needs.
        $min = (float) apply_filters('taler_wp_min_amount', 0.01);
        $max = (float) apply_filters('taler_wp_max_amount', 1000000.00);

        if ($number < $min || $number > $max) {
            return null;
        }

        // Preserve original numeric precision instead of rewriting it.
        return $currency . ':' . $matches[2];
    }
}
