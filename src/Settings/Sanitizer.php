<?php
namespace TalerPayments\Settings;

use TalerPayments\Helpers\Crypto;
use TalerPayments\Settings\DTO\SanitizeContext;
use TalerPayments\Settings\DTO\SanitizeResult;
use TalerPayments\Services\MerchantAuthConfigurator;
use TalerPayments\Services\SettingsNotices;

/**
 * Settings API sanitize callback for `taler_options`.
 */
final class Sanitizer
{
    public function __construct(
        private readonly SettingsNotices $notices,
    ) {
    }

    /**
     * @param mixed $input
     * @param array<string,mixed> $currentOptions
     */
    public function sanitize($input, SanitizeContext $context, array $currentOptions): SanitizeResult
    {
        $old = is_array($currentOptions) ? $currentOptions : [];

        if (!current_user_can('manage_options')) {
            // If this ever triggers, WordPress will still block saving, but this keeps the callback safe.
            $this->addNoticeError('taler_options_permission_denied', __('You do not have permission to do this.', 'taler-payments'));
            return SanitizeResult::withoutLoginCheck($old);
        }

        $new = $old;
        $input = is_array($input) ? $input : [];
        $option_page = $context->optionPage();
        $is_delete = $context->isDelete();

        return match ($option_page) {
            SettingsFormMap::GROUP_BASEURL => $this->sanitizeBaseUrlGroup($input, $old, $new, $is_delete),
            SettingsFormMap::GROUP_USERPASS => $this->sanitizeUserPassGroup($input, $old, $new, $is_delete),
            SettingsFormMap::GROUP_TOKEN => $this->sanitizeTokenGroup($input, $old, $new, $is_delete),
            default => SanitizeResult::withoutLoginCheck($old), // Unknown option page (unexpected). Don't change anything.
        };
    }

    private function addNoticeError(string $code, string $message): void
    {
        $this->notices->addOnce(Options::OPTION_NAME, $code, $message, 'error');
    }

    private function addNoticeUpdated(string $code, string $message): void
    {
        $this->notices->addOnce(Options::OPTION_NAME, $code, $message, 'updated');
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    private function sanitizeBaseUrlGroup(array $input, array $old, array $new, bool $isDelete): SanitizeResult
    {
        if ($isDelete) {
            unset($new['taler_base_url']);
            $this->addNoticeUpdated('taler_baseurl_deleted', __('Base URL deleted.', 'taler-payments'));
            return SanitizeResult::withoutLoginCheck($new);
        }

        $base_url = isset($input['taler_base_url']) ? (string) wp_unslash($input['taler_base_url']) : '';
        $base_url = trim($base_url);

        if ($base_url === '') {
            $this->addNoticeError('taler_baseurl_required', __('Please provide a base URL.', 'taler-payments'));
            return SanitizeResult::withoutLoginCheck($old);
        }

        $base_url = esc_url_raw($base_url, ['https']);
        $parsed = wp_parse_url($base_url);
        $scheme = is_array($parsed) && isset($parsed['scheme']) ? strtolower((string) $parsed['scheme']) : '';
        if ($base_url === '' || $scheme !== 'https') {
            $this->addNoticeError('taler_baseurl_invalid', __('Base URL must start with https://', 'taler-payments'));
            return SanitizeResult::withoutLoginCheck($old);
        }

        $new['taler_base_url'] = $base_url;
        return SanitizeResult::withLoginCheck($new, MerchantAuthConfigurator::MODE_AUTO);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    private function sanitizeUserPassGroup(array $input, array $old, array $new, bool $isDelete): SanitizeResult
    {
        // Deleting credentials should bypass HTML required validation via `formnovalidate` on the delete button.
        if ($isDelete) {
            unset($new['ext_username'], $new['ext_password'], $new['taler_instance']);
            $this->addNoticeUpdated('taler_userpass_deleted', __('Username and password deleted.', 'taler-payments'));
            return SanitizeResult::withoutLoginCheck($new);
        }

        $username = isset($input['ext_username']) ? sanitize_text_field(wp_unslash($input['ext_username'])) : '';
        $password = isset($input['ext_password']) ? (string) wp_unslash($input['ext_password']) : '';
        $instance = isset($input['taler_instance']) ? sanitize_text_field(wp_unslash($input['taler_instance'])) : '';

        if ($username === '') {
            $this->addNoticeError('taler_username_required', __('Please provide a username.', 'taler-payments'));
            return SanitizeResult::withoutLoginCheck($old);
        }

        if ($instance === '') {
            $this->addNoticeError('taler_instance_required', __('Please provide an instance ID.', 'taler-payments'));
            return SanitizeResult::withoutLoginCheck($old);
        }

        $already_has_password = !empty($old['ext_password']);
        if ($password === '' && !$already_has_password) {
            $this->addNoticeError('taler_password_required', __('Please provide a password.', 'taler-payments'));
            return SanitizeResult::withoutLoginCheck($old);
        }

        $new['ext_username'] = $username;
        $new['taler_instance'] = $instance;

        if ($password !== '') {
            $encrypted_password = $this->encryptValueOrNotify(
                $password,
                'taler_userpass_encrypt_failed',
                __('Could not encrypt password. Credentials were not saved.', 'taler-payments')
            );
            if ($encrypted_password === null) {
                return SanitizeResult::withoutLoginCheck($old);
            }
            $new['ext_password'] = $encrypted_password;
        }

        return SanitizeResult::withLoginCheck($new, MerchantAuthConfigurator::MODE_USERPASS);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    private function sanitizeTokenGroup(array $input, array $old, array $new, bool $isDelete): SanitizeResult
    {
        if ($isDelete) {
            unset($new['taler_token']);
            $this->addNoticeUpdated('taler_token_deleted', __('Access token deleted.', 'taler-payments'));
            return SanitizeResult::withoutLoginCheck($new);
        }

        $token = isset($input['taler_token']) ? (string) wp_unslash($input['taler_token']) : '';
        if ($token === '') {
            $this->addNoticeError('taler_token_required', __('Please provide an access token.', 'taler-payments'));
            return SanitizeResult::withoutLoginCheck($old);
        }

        $encrypted_token = $this->encryptValueOrNotify(
            $token,
            'taler_token_encrypt_failed',
            __('Could not encrypt access token. Token was not saved.', 'taler-payments')
        );
        if ($encrypted_token === null) {
            return SanitizeResult::withoutLoginCheck($old);
        }

        $new['taler_token'] = $encrypted_token;
        return SanitizeResult::withLoginCheck($new, MerchantAuthConfigurator::MODE_TOKEN);
    }

    private function encryptValueOrNotify(string $value, string $noticeCode, string $failureMessage): ?string
    {
        $encrypted = Crypto::encryptString($value);
        if ($encrypted === '') {
            $this->addNoticeError($noticeCode, $failureMessage);
            return null;
        }

        return $encrypted;
    }
}

