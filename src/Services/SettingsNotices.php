<?php
namespace TalerPayments\Services;

/**
 * Wrapper around the WordPress Settings API notice system.
 */
final class SettingsNotices
{
    /**
     * Add a settings notice message (error/updated/info), but only once per request/code.
     */
    public function addOnce(string $setting, string $code, string $message, string $type = 'error'): void
    {
        static $added = [];

        if (isset($added[$setting]) && isset($added[$setting][$code])) {
            return;
        }

        $existing = get_settings_errors($setting);
        foreach ($existing as $err) {
            if (!empty($err['code']) && $err['code'] === $code) {
                return;
            }
        }

        add_settings_error($setting, $code, $message, $type);
        $added[$setting][$code] = true;
    }
}

