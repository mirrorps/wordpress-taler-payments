<?php
namespace TalerPayments\Services;

use TalerPayments\Helpers\Crypto;

/**
 * Performs a lightweight merchant backend login/config check and surfaces settings notices.
 */
final class MerchantBackendChecker
{
    public function __construct(
        private readonly SettingsNotices $notices
    ) {
    }

    /**
     * Normalize auth token value for SDK (expects full Authorization header value).
     */
    public function normalizeAuthToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }
        // If user pasted just the opaque token, assume Bearer.
        if (!preg_match('/^(Bearer|Basic)\s+/i', $token)) {
            return 'Bearer ' . $token;
        }
        return $token;
    }

    /**
     * Checks are only run when:
     * - base_url is set, AND
     * - (token is set) OR (username+password+instance are set)
     *
     * @param array<string, mixed> $options Merged taler_options (already saved shape)
     */
    public function testLogin(array $options, string $mode = 'auto'): void
    {
        $baseUrl = isset($options['taler_base_url']) ? trim((string) $options['taler_base_url']) : '';
        if ($baseUrl === '') {
            return;
        }

        // Duplicate guard: prevent duplicate checks/notices per request.
        static $ran = [];
        $runKey = $mode . '|' . md5($baseUrl);
        if (isset($ran[$runKey])) {
            return;
        }
        $ran[$runKey] = true;

        $tokenEnc = isset($options['taler_token']) ? (string) $options['taler_token'] : '';
        $token = $tokenEnc !== '' ? Crypto::decryptString($tokenEnc) : '';
        $token = $this->normalizeAuthToken($token);

        $username = isset($options['ext_username']) ? trim((string) $options['ext_username']) : '';
        $passwordEnc = isset($options['ext_password']) ? (string) $options['ext_password'] : '';
        $password = $passwordEnc !== '' ? Crypto::decryptString($passwordEnc) : '';
        $instance = isset($options['taler_instance']) ? trim((string) $options['taler_instance']) : '';

        $authLabel = null;
        $credentialHint = __('credentials', 'taler-payments');
        $factoryOptions = [
            'base_url' => $baseUrl,
        ];

        if ($mode === 'token') {
            // Token-only mode: do NOT fall back to username/password.
            if ($token === '') {
                return;
            }
            $authLabel = __('Access Token', 'taler-payments');
            $credentialHint = __('access token', 'taler-payments');
            $factoryOptions['token'] = $token;
        } elseif ($mode === 'userpass') {
            // Username/password-only mode: do NOT fall back to access token.
            if ($username === '' || $password === '' || $instance === '') {
                return;
            }
            $authLabel = __('Username & Password', 'taler-payments');
            $credentialHint = __('username, password, and instance ID', 'taler-payments');
            $factoryOptions['username'] = $username;
            $factoryOptions['password'] = $password;
            $factoryOptions['instance'] = $instance;
            // Keep the test conservative; readonly is enough to verify auth works.
            $factoryOptions['scope'] = 'readonly';
            $factoryOptions['duration_us'] = 3600_000_000;
            $factoryOptions['description'] = 'WordPress taler-payments settings check';
        } else {
            // Auto mode: access token has priority if both are configured.
            if ($token !== '') {
                $authLabel = __('Access Token', 'taler-payments');
                $credentialHint = __('access token', 'taler-payments');
                $factoryOptions['token'] = $token;
            } elseif ($username !== '' && $password !== '' && $instance !== '') {
                $authLabel = __('Username & Password', 'taler-payments');
                $credentialHint = __('username, password, and instance ID', 'taler-payments');
                $factoryOptions['username'] = $username;
                $factoryOptions['password'] = $password;
                $factoryOptions['instance'] = $instance;
                // Keep the test conservative; readonly is enough to verify auth works.
                $factoryOptions['scope'] = 'readonly';
                $factoryOptions['duration_us'] = 3600_000_000;
                $factoryOptions['description'] = 'WordPress taler-payments settings check';
            } else {
                return;
            }
        }

        try {
            $taler = \Taler\Factory\Factory::create($factoryOptions);
            $report = $taler->configCheck();

            if (!is_array($report) || empty($report['ok'])) {
                // Extract most useful failure hint.
                $step = 'auth';
                $status = null;
                $error = null;

                if (isset($report['config']) && is_array($report['config']) && empty($report['config']['ok'])) {
                    $step = 'config';
                    $status = $report['config']['status'] ?? null;
                    $error = $report['config']['error'] ?? null;
                } elseif (isset($report['instance']) && is_array($report['instance']) && empty($report['instance']['ok'])) {
                    $step = 'instance';
                    $status = $report['instance']['status'] ?? null;
                    $error = $report['instance']['error'] ?? null;
                } elseif (isset($report['auth']) && is_array($report['auth']) && empty($report['auth']['ok'])) {
                    $step = 'auth';
                    $status = $report['auth']['status'] ?? null;
                    $error = $report['auth']['error'] ?? null;
                }

                $statusText = is_int($status) ? (' (HTTP ' . $status . ')') : '';
                $errorText = is_string($error) && $error !== '' ? (' ' . $error) : '';

                $this->notices->addOnce(
                    'taler_options',
                    'taler_backend_login_failed',
                    sprintf(
                        /* translators: 1: auth method label, 2: failing step, 3: optional status text, 4: optional error slug */
                        __('Merchant backend login test failed (error: %1$s): %2$s%3$s.%4$s', 'taler-payments'),
                        $authLabel,
                        $step,
                        $statusText,
                        $errorText
                    ),
                    'error'
                );
                return;
            }

            $this->notices->addOnce(
                'taler_options',
                'taler_backend_login_ok',
                sprintf(
                    __('Merchant backend login test successful (%s).', 'taler-payments'),
                    $authLabel
                ),
                'updated'
            );
        } catch (\InvalidArgumentException $e) {
            $this->notices->addOnce(
                'taler_options',
                'taler_backend_login_invalid',
                __('Merchant backend login test failed: invalid configuration (is this a Taler Merchant Backend base URL?).', 'taler-payments'),
                'error'
            );
        } catch (\Throwable $e) {
            // Avoid leaking sensitive info; keep message generic.
            $this->notices->addOnce(
                'taler_options',
                'taler_backend_login_exception',
                sprintf(
                    /* translators: 1: auth method label, 2: credentials hint */
                    __('Merchant backend login test failed (error: %1$s). Please verify Base URL and %2$s.', 'taler-payments'),
                    (string) $authLabel,
                    $credentialHint
                ),
                'error'
            );
        }
    }
}

