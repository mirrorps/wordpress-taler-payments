<?php
namespace TalerPayments\Settings;

use TalerPayments\Helpers\Crypto;
use TalerPayments\Services\MerchantAuthConfigurator;
use TalerPayments\Services\MerchantBackendChecker;
use TalerPayments\Services\SettingsNotices;

/**
 * Settings API sanitize callback for `taler_options`.
 *
 * Uses `$_POST['option_page']` to know which form was submitted.
 */
final class Sanitizer
{
    public function __construct(
        private readonly SettingsNotices $notices,
        private readonly MerchantBackendChecker $checker,
    ) {
    }

    /**
     * @param mixed $input
     * @return array<string, mixed>
     */
    public function sanitize($input): array
    {
        if (!current_user_can('manage_options')) {
            // If this ever triggers, WordPress will still block saving, but this keeps the callback safe.
            $this->notices->addOnce(
                Options::OPTION_NAME,
                'taler_options_permission_denied',
                __('You do not have permission to do this.', 'taler-payments'),
                'error'
            );
            return Options::get();
        }

        $old = Options::get();
        $new = is_array($old) ? $old : [];

        $input = is_array($input) ? $input : [];

        $option_page = isset($_POST['option_page']) ? sanitize_text_field(wp_unslash($_POST['option_page'])) : '';

        if ($option_page === 'taler_baseurl_group') {
            $is_delete = !empty($_POST['taler_baseurl_delete']);
            if ($is_delete) {
                unset($new['taler_base_url']);
                $this->notices->addOnce(
                    Options::OPTION_NAME,
                    'taler_baseurl_deleted',
                    __('Base URL deleted.', 'taler-payments'),
                    'updated'
                );
                return $new;
            }

            $base_url = isset($input['taler_base_url']) ? (string) wp_unslash($input['taler_base_url']) : '';
            $base_url = trim($base_url);

            if ($base_url === '') {
                $this->notices->addOnce(
                    Options::OPTION_NAME,
                    'taler_baseurl_required',
                    __('Please provide a base URL.', 'taler-payments'),
                    'error'
                );
                return $old;
            }

            $base_url = esc_url_raw($base_url, ['https']);
            $parsed = wp_parse_url($base_url);
            $scheme = is_array($parsed) && isset($parsed['scheme']) ? strtolower((string) $parsed['scheme']) : '';

            if ($base_url === '' || $scheme !== 'https') {
                $this->notices->addOnce(
                    Options::OPTION_NAME,
                    'taler_baseurl_invalid',
                    __('Base URL must start with https://', 'taler-payments'),
                    'error'
                );
                return $old;
            }

            $new['taler_base_url'] = $base_url;

            // If credentials are present, verify we can reach/authenticate.
            $this->checker->testLogin($new, MerchantAuthConfigurator::MODE_AUTO);
            return $new;
        }

        if ($option_page === 'taler_userpass_group') {
            // Deleting credentials should bypass HTML required validation via `formnovalidate` on the delete button.
            $is_delete = !empty($_POST['taler_userpass_delete']);
            if ($is_delete) {
                unset($new['ext_username'], $new['ext_password'], $new['taler_instance']);
                $this->notices->addOnce(
                    Options::OPTION_NAME,
                    'taler_userpass_deleted',
                    __('Username and password deleted.', 'taler-payments'),
                    'updated'
                );
                return $new;
            }

            $username = isset($input['ext_username']) ? sanitize_text_field(wp_unslash($input['ext_username'])) : '';
            $password = isset($input['ext_password']) ? (string) wp_unslash($input['ext_password']) : '';
            $instance = isset($input['taler_instance']) ? sanitize_text_field(wp_unslash($input['taler_instance'])) : '';

            if ($username === '') {
                $this->notices->addOnce(
                    Options::OPTION_NAME,
                    'taler_username_required',
                    __('Please provide a username.', 'taler-payments'),
                    'error'
                );
                return $old;
            }

            if ($instance === '') {
                $this->notices->addOnce(
                    Options::OPTION_NAME,
                    'taler_instance_required',
                    __('Please provide an instance ID.', 'taler-payments'),
                    'error'
                );
                return $old;
            }

            $already_has_password = !empty($old['ext_password']);
            if ($password === '' && !$already_has_password) {
                $this->notices->addOnce(
                    Options::OPTION_NAME,
                    'taler_password_required',
                    __('Please provide a password.', 'taler-payments'),
                    'error'
                );
                return $old;
            }

            $new['ext_username'] = $username;
            $new['taler_instance'] = $instance;
            if ($password !== '') {
                $encrypted_password = Crypto::encryptString($password);
                if ($encrypted_password === '') {
                    $this->notices->addOnce(
                        Options::OPTION_NAME,
                        'taler_userpass_encrypt_failed',
                        __('Could not encrypt password. Credentials were not saved.', 'taler-payments'),
                        'error'
                    );
                    return $old;
                }
                $new['ext_password'] = $encrypted_password;
            }

            // If base URL is present, verify we can reach/authenticate.
            $this->checker->testLogin($new, MerchantAuthConfigurator::MODE_USERPASS);
            return $new;
        }

        if ($option_page === 'taler_token_group') {
            $is_delete = !empty($_POST['taler_token_delete']);
            if ($is_delete) {
                unset($new['taler_token']);
                $this->notices->addOnce(
                    Options::OPTION_NAME,
                    'taler_token_deleted',
                    __('Access token deleted.', 'taler-payments'),
                    'updated'
                );
                return $new;
            }

            $token = isset($input['taler_token']) ? (string) wp_unslash($input['taler_token']) : '';

            if ($token === '') {
                $this->notices->addOnce(
                    Options::OPTION_NAME,
                    'taler_token_required',
                    __('Please provide an access token.', 'taler-payments'),
                    'error'
                );
                return $old;
            }

            $encrypted_token = Crypto::encryptString($token);
            if ($encrypted_token === '') {
                $this->notices->addOnce(
                    Options::OPTION_NAME,
                    'taler_token_encrypt_failed',
                    __('Could not encrypt access token. Token was not saved.', 'taler-payments'),
                    'error'
                );
                return $old;
            }

            $new['taler_token'] = $encrypted_token;

            // If base URL is present, verify we can reach/authenticate.
            $this->checker->testLogin($new, MerchantAuthConfigurator::MODE_TOKEN);
            return $new;
        }

        // Unknown option page (unexpected). Don’t change anything.
        return $old;
    }
}

