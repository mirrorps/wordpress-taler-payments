<?php

// SECURITY: Only load in admin
if (!is_admin()) {
    return;
}


/**
 * Retrieve Taler credentials anywhere:
 *
$creds = get_option('taler_options');
$username = $creds['ext_username'] ?? '';
$password = taler_decrypt_str($creds['ext_password'] ?? '');
var_dump($username, $password);exit;
 *
*/

// $creds = get_option('taler_options');
// $username = $creds['ext_username'] ?? '';
// $password = taler_decrypt_str($creds['ext_password'] ?? '');
// $token = taler_decrypt_str($creds['taler_token'] ?? '');
// var_dump($username, $password, $token);
// exit;

// === SETTINGS PAGE ===
add_action('admin_menu', 'taler_add_admin_page');
add_action('admin_enqueue_scripts', 'taler_admin_enqueue_assets');
add_action('admin_init', 'taler_register_settings');

function taler_add_admin_page() {
    add_options_page(
        __('Taler Payments Settings', 'taler-payments'),
        __('Taler Payments', 'taler-payments'),
        'manage_options',
        'taler-payments',
        'taler_settings_page'
    );
}

function taler_admin_enqueue_assets(string $hook): void
{
    if ($hook !== 'settings_page_taler-payments') {
        return;
    }

    $css_path = plugin_dir_path(__FILE__) . '../assets/taler-admin.css';
    $css_url  = plugin_dir_url(__FILE__) . '../assets/taler-admin.css';
    $ver      = @filemtime($css_path) ?: (defined('TALER_PAYMENTS_VERSION') ? TALER_PAYMENTS_VERSION : '1.0.0');

    wp_enqueue_style('taler-payments-admin', $css_url, [], $ver);
}

function taler_get_options(): array
{
    $options = get_option('taler_options');
    return is_array($options) ? $options : [];
}

function taler_register_settings(): void
{
    register_setting('taler_baseurl_group', 'taler_options', [
        'type'              => 'array',
        'sanitize_callback' => 'taler_options_sanitize',
        'default'           => [],
    ]);

    // Keep the forms separate by registering settings groups, but store everything in one option array.
    register_setting('taler_userpass_group', 'taler_options', [
        'type'              => 'array',
        'sanitize_callback' => 'taler_options_sanitize',
        'default'           => [],
    ]);

    register_setting('taler_token_group', 'taler_options', [
        'type'              => 'array',
        'sanitize_callback' => 'taler_options_sanitize',
        'default'           => [],
    ]);
}

/**
 * Add a settings notice message (error/updated/info), but only once per request/code.
 */
function taler_add_settings_notice_once(string $setting, string $code, string $message, string $type = 'error'): void
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

/**
 * Normalize auth token value for SDK (expects full Authorization header value).
 */
function taler_normalize_auth_token(string $token): string
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
 * Perform a lightweight login/config check against the merchant backend and
 * surface a settings notice (success or error).
 *
 * Checks are only run when:
 * - base_url is set, AND
 * - (token is set) OR (username+password+instance are set)
 *
 * @param array<string, mixed> $options Merged taler_options (already saved shape)
 */
function taler_test_merchant_backend_login(array $options, string $mode = 'auto'): void
{
    $baseUrl = isset($options['taler_base_url']) ? trim((string) $options['taler_base_url']) : '';
    if ($baseUrl === '') {
        return;
    }

    // Duplicate guard: Prevent duplicate checks/notices (will be not triggered, but it's good to have it in place)
    static $ran = [];
    $runKey = $mode . '|' . md5($baseUrl);
    if (isset($ran[$runKey])) {
        return;
    }
    $ran[$runKey] = true;

    $tokenEnc = isset($options['taler_token']) ? (string) $options['taler_token'] : '';
    $token = $tokenEnc !== '' ? taler_decrypt_str($tokenEnc) : '';
    $token = taler_normalize_auth_token($token);

    $username = isset($options['ext_username']) ? trim((string) $options['ext_username']) : '';
    $passwordEnc = isset($options['ext_password']) ? (string) $options['ext_password'] : '';
    $password = $passwordEnc !== '' ? taler_decrypt_str($passwordEnc) : '';
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

            taler_add_settings_notice_once(
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

        taler_add_settings_notice_once(
            'taler_options',
            'taler_backend_login_ok',
            sprintf(
                __('Merchant backend login test successful (%s).', 'taler-payments'),
                $authLabel
            ),
            'updated'
        );
    } catch (\InvalidArgumentException $e) {
        taler_add_settings_notice_once(
            'taler_options',
            'taler_backend_login_invalid',
            __('Merchant backend login test failed: invalid configuration (is this a Taler Merchant Backend base URL?).', 'taler-payments'),
            'error'
        );
    } catch (\Throwable $e) {
        // Avoid leaking sensitive info; keep message generic.
        taler_add_settings_notice_once(
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

/**
 * Settings API sanitize callback for `taler_options`.
 *
 * Uses `$_POST['option_page']` to know which form was submitted.
 */
function taler_options_sanitize($input): array
{
    if (!current_user_can('manage_options')) {
        // If this ever triggers, WordPress will still block saving, but this keeps the callback safe.
        taler_add_settings_notice_once(
            'taler_options',
            'taler_options_permission_denied',
            __('You do not have permission to do this.', 'taler-payments'),
            'error'
        );
        return taler_get_options();
    }

    $old = taler_get_options();
    $new = is_array($old) ? $old : [];

    $input = is_array($input) ? $input : [];

    $option_page = isset($_POST['option_page']) ? sanitize_text_field(wp_unslash($_POST['option_page'])) : '';

    if ($option_page === 'taler_baseurl_group') {
        $is_delete = !empty($_POST['taler_baseurl_delete']);
        if ($is_delete) {
            unset($new['taler_base_url']);
            taler_add_settings_notice_once(
                'taler_options',
                'taler_baseurl_deleted',
                __('Base URL deleted.', 'taler-payments'),
                'updated'
            );
            return $new;
        }

        $base_url = isset($input['taler_base_url']) ? (string) wp_unslash($input['taler_base_url']) : '';
        $base_url = trim($base_url);

        if ($base_url === '') {
            taler_add_settings_notice_once(
                'taler_options',
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
            taler_add_settings_notice_once(
                'taler_options',
                'taler_baseurl_invalid',
                __('Base URL must start with https://', 'taler-payments'),
                'error'
            );
            return $old;
        }

        $new['taler_base_url'] = $base_url;
        // If credentials are present, verify we can reach/authenticate.
        taler_test_merchant_backend_login($new, 'auto');
        return $new;
    }

    if ($option_page === 'taler_userpass_group') {
        // Deleting credentials should bypass HTML required validation via `formnovalidate` on the delete button.
        $is_delete = !empty($_POST['taler_userpass_delete']);
        if ($is_delete) {
            unset($new['ext_username'], $new['ext_password'], $new['taler_instance']);
            taler_add_settings_notice_once(
                'taler_options',
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
            taler_add_settings_notice_once(
                'taler_options',
                'taler_username_required',
                __('Please provide a username.', 'taler-payments'),
                'error'
            );
            return $old;
        }

        if ($instance === '') {
            taler_add_settings_notice_once(
                'taler_options',
                'taler_instance_required',
                __('Please provide an instance ID.', 'taler-payments'),
                'error'
            );
            return $old;
        }

        $already_has_password = !empty($old['ext_password']);
        if ($password === '' && !$already_has_password) {
            taler_add_settings_notice_once(
                'taler_options',
                'taler_password_required',
                __('Please provide a password.', 'taler-payments'),
                'error'
            );
            return $old;
        }

        $new['ext_username'] = $username;
        $new['taler_instance'] = $instance;
        if ($password !== '') {
            $encrypted_password = taler_encrypt_str($password);
            if ($encrypted_password === '') {
                taler_add_settings_notice_once(
                    'taler_options',
                    'taler_userpass_encrypt_failed',
                    __('Could not encrypt password. Credentials were not saved.', 'taler-payments'),
                    'error'
                );
                return $old;
            }
            $new['ext_password'] = $encrypted_password;
        }

        // If base URL is present, verify we can reach/authenticate.
        taler_test_merchant_backend_login($new, 'userpass');
        return $new;
    }

    if ($option_page === 'taler_token_group') {
        $is_delete = !empty($_POST['taler_token_delete']);
        if ($is_delete) {
            unset($new['taler_token']);
            taler_add_settings_notice_once(
                'taler_options',
                'taler_token_deleted',
                __('Access token deleted.', 'taler-payments'),
                'updated'
            );
            return $new;
        }

        $token = isset($input['taler_token']) ? (string) wp_unslash($input['taler_token']) : '';

        if ($token === '') {
            taler_add_settings_notice_once(
                'taler_options',
                'taler_token_required',
                __('Please provide an access token.', 'taler-payments'),
                'error'
            );
            return $old;
        }

        $encrypted_token = taler_encrypt_str($token);
        if ($encrypted_token === '') {
            taler_add_settings_notice_once(
                'taler_options',
                'taler_token_encrypt_failed',
                __('Could not encrypt access token. Token was not saved.', 'taler-payments'),
                'error'
            );
            return $old;
        }

        $new['taler_token'] = $encrypted_token;
        
        // If base URL is present, verify we can reach/authenticate.
        taler_test_merchant_backend_login($new, 'token');
        return $new;
    }

    // Unknown option page (unexpected). Don’t change anything.
    return $old;
}

function taler_settings_page() {
    $options = taler_get_options();

    $saved_username = $options['ext_username'] ?? '';
    $username = $saved_username;
    $saved_instance = $options['taler_instance'] ?? '';
    $instance = (string) $saved_instance;
    // Never pre-fill stored secrets in password fields.
    $password = '';
    $token_value = '';

    $has_userpass = ($saved_username !== '') || !empty($options['ext_password']) || ($instance !== '');
    $has_token = !empty($options['taler_token']);
    $saved_base_url = isset($options['taler_base_url']) ? (string) $options['taler_base_url'] : '';
    $has_base_url = ($saved_base_url !== '');

    $delete_confirm = __('Deleting credentials is irreversible. Are you sure you want to continue?', 'taler-payments');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        
        <div class="taler-settings-group">
            <h2><?php echo esc_html__('Taler Merchant Backend', 'taler-payments'); ?></h2>

            <div class="notice notice-info inline">
                <p><?php
                    echo wp_kses_post(sprintf(
                        __('<strong>Security:</strong> Passwords and tokens are first <strong>encrypted</strong> and then stored in the database.', 'taler-payments')
                    ));
                ?></p>
            </div>

            <div class="notice notice-info inline">
                <p><?php echo wp_kses_post(sprintf(
                    __('To access the <strong>Taler Merchant Backend</strong> provide either <strong>%1$s</strong> or an <strong>%2$s</strong>. If both are supplied, the <strong>%2$s</strong> takes priority.', 'taler-payments'),
                    esc_html__('Username & Password', 'taler-payments'),
                    esc_html__('Access Token', 'taler-payments')
                )); ?>
                </p>
            </div>

            <hr class="taler-divider" />

            <h3 class="taler-settings-subheading"><?php echo esc_html__('Base URL', 'taler-payments'); ?></h3>
            <form id="taler-baseurl-form" method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
                <?php settings_fields('taler_baseurl_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="taler-base-url"><?php echo esc_html__('Taler Base URL *', 'taler-payments'); ?></label></th>
                        <td>
                            <input
                                type="text"
                                id="taler-base-url"
                                name="taler_options[taler_base_url]"
                                value="<?php echo esc_attr($saved_base_url); ?>"
                                class="regular-text ltr"
                                placeholder="https://backend.demo.taler.net/instances/sandbox"
                                required
                            />
                            <p class="description">
                                <?php echo wp_kses_post(__(
                                    'Important: the Base URL must include the instance path (e.g. /instances/<instance-id>) and must start with https://.<br>Example: https://backend.demo.taler.net/instances/sandbox', 
                                    'taler-payments'
                                )); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <div class="taler-form-actions">
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Save Changes', 'taler-payments'); ?></button>
                    <?php if ($has_base_url) : ?>
                        <button
                            type="submit"
                            name="taler_baseurl_delete"
                            value="1"
                            class="button taler-delete-button"
                            formnovalidate
                            onclick="return confirm('<?php echo esc_attr($delete_confirm); ?>');"
                        ><?php echo esc_html__('Delete', 'taler-payments'); ?></button>
                    <?php endif; ?>
                </div>
            </form>

            <hr class="taler-divider" />

            <h3 class="taler-settings-subheading"><?php echo esc_html__('Username & Password', 'taler-payments'); ?></h3>
            <form id="taler-userpass-form" method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
                <?php settings_fields('taler_userpass_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="taler-instance"><?php echo esc_html__('Instance ID *', 'taler-payments'); ?></label></th>
                        <td>
                            <input
                                type="text"
                                id="taler-instance"
                                name="taler_options[taler_instance]"
                                value="<?php echo esc_attr($instance); ?>"
                                class="regular-text"
                                required
                            />
                            <p class="description">
                                <?php echo wp_kses_post(__(
                                    'Required when authenticating with Username & Password<br>The instance ID specifies which Taler Merchant Backend instance to authenticate against.', 
                                    'taler-payments'
                                )); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="taler-ext-username"><?php echo esc_html__('Username *', 'taler-payments'); ?></label></th>
                        <td>
                            <input type="text" id="taler-ext-username" name="taler_options[ext_username]" value="<?php echo esc_attr($username); ?>" class="regular-text" required />
                            <p class="description"><?php echo esc_html__('Username for the Taler Merchant Backend instance.', 'taler-payments'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="taler-ext-password"><?php echo esc_html__('Password *', 'taler-payments'); ?></label></th>
                        <td>
                            <input
                                type="password"
                                id="taler-ext-password"
                                name="taler_options[ext_password]"
                                value="<?php echo esc_attr($password); ?>"
                                <?php echo $has_userpass ? 'placeholder="' . esc_attr__('(stored)', 'taler-payments') . '"' : ''; ?>
                                class="regular-text ltr"
                                autocomplete="new-password"
                                <?php echo $has_userpass ? '' : 'required'; ?>
                            />
                            <p class="description">
                                <?php echo wp_kses_post(__(
                                    'Password for the Taler Merchant Backend instance.<br>The password will be first <strong>encrypted</strong> and then stored in the database.',
                                    'taler-payments'
                                )); ?>
                                <?php if ($has_userpass) : ?>
                                    <?php echo ' ' . esc_html__('Leave blank to keep the stored password.', 'taler-payments'); ?>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <div class="taler-form-actions">
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Save Changes', 'taler-payments'); ?></button>
                    <?php if ($has_userpass) : ?>
                        <button
                            type="submit"
                            name="taler_userpass_delete"
                            value="1"
                            class="button taler-delete-button"
                            formnovalidate
                            onclick="return confirm('<?php echo esc_attr($delete_confirm); ?>');"
                        ><?php echo esc_html__('Delete', 'taler-payments'); ?></button>
                    <?php endif; ?>
                </div>
            </form>

            <hr class="taler-divider" />

            <h3 class="taler-settings-subheading"><?php echo esc_html__('Access Token', 'taler-payments'); ?></h3>
            <form id="taler-token-form" method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
                <?php settings_fields('taler_token_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="taler-token"><?php echo esc_html__('Access Token *', 'taler-payments'); ?></label></th>
                        <td>
                            <input
                                type="password"
                                id="taler-token"
                                name="taler_options[taler_token]"
                                value="<?php echo esc_attr($token_value); ?>"
                                <?php echo $has_token ? 'placeholder="' . esc_attr__('(stored)', 'taler-payments') . '"' : ''; ?>
                                class="regular-text ltr"
                                autocomplete="new-password"
                                required
                            />
                            <p class="description"><?php echo wp_kses_post(__('Access token for the Taler Merchant Backend.<br>Save the full value as used in the HTTP <code>Authorization</code> header (including the prefix). Example: <code>Bearer secret-token:sandbox</code>.<br>The access token will be first <strong>encrypted</strong> and then stored in the database.', 'taler-payments')); ?></p>
                        </td>
                    </tr>
                </table>
                <div class="taler-form-actions">
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Save Changes', 'taler-payments'); ?></button>
                    <?php if ($has_token) : ?>
                        <button
                            type="submit"
                            name="taler_token_delete"
                            value="1"
                            class="button taler-delete-button"
                            formnovalidate
                            onclick="return confirm('<?php echo esc_attr($delete_confirm); ?>');"
                        ><?php echo esc_html__('Delete', 'taler-payments'); ?></button>
                    <?php endif; ?>
                </div>
            </form>
            <hr class="taler-divider" />
        </div>
    </div>
    <?php
}
