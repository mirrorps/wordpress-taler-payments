<?php

// SECURITY: Only load in admin
 if (!is_admin()) return;


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
function taler_add_admin_page() {
    add_options_page(
        'Taler Payments Settings', 
        'Taler Payments', 
        'manage_options', 
        'taler-payments',
        'taler_settings_page'
    );
}

add_action('admin_init', 'taler_register_settings');
function taler_register_settings() {
    register_setting('taler_options', 'taler_options', 'taler_sanitize');
    add_settings_section('taler_main_section', 'Taler Merchant Backend', 'taler_main_section_cb', 'taler');
    add_settings_field('ext_username', 'Username', 'taler_username_cb', 'taler', 'taler_main_section');
    add_settings_field('ext_password', 'Password', 'taler_password_cb', 'taler', 'taler_main_section');
    add_settings_field('taler_token', 'Access Token', 'taler_token_cb', 'taler', 'taler_main_section');
}

function taler_main_section_cb() {
    echo '<p class="description">Provide either <strong>Username + Password</strong> or an <strong>Access Token</strong>.</p>';
}

function taler_username_cb() {
    $options = get_option('taler_options');
    echo '<input type="text" name="taler_options[ext_username]" value="' . 
         esc_attr($options['ext_username'] ?? '') . 
         '" class="regular-text" />';
    echo '<p class="description">Username/API key for Taler external system.</p>';
}

function taler_password_cb() {
    $options = get_option('taler_options');
    $encrypted = $options['ext_password'] ?? '';
    // IMPORTANT: Stored value is encrypted; decrypt before showing in admin UI.
    $value = taler_decrypt_str((string) $encrypted);
    echo '<input type="password" name="taler_options[ext_password]" value="' . 
         esc_attr($value) . 
         '" class="regular-text ltr" autocomplete="new-password" />';
    echo '<p class="description">Password will be <strong>encrypted</strong> before storage.</p>';
}

function taler_token_cb() {
    $options = get_option('taler_options');
    $has_saved = !empty($options['taler_token']);

    $placeholder = $has_saved ? 'placeholder="(stored)"' : '';

    // SECURITY: Never echo the decrypted token back into the UI.
    echo '<input type="password" name="taler_options[taler_token]" value="" ' .
        $placeholder . ' class="regular-text ltr" autocomplete="new-password" />';

    echo '<p class="description">Leave blank to keep the existing token. Tokens are <strong>encrypted</strong> before storage.</p>';
}

function taler_sanitize($input) {
    $existing = get_option('taler_options');
    $existing = is_array($existing) ? $existing : [];

    $sanitized = [];
    $sanitized['ext_username'] = sanitize_text_field($input['ext_username'] ?? '');
    
    if (!empty($existing['ext_password'])) {
        $sanitized['ext_password'] = $existing['ext_password'];
    }

    // ENCRYPT password before saving to DB (only when user provides a new value)
    if (array_key_exists('ext_password', $input) && $input['ext_password'] !== '') {
        $encrypted = taler_encrypt_str((string) $input['ext_password']);
        if ($encrypted !== '') {
            $sanitized['ext_password'] = $encrypted;
        }
        // If encryption fails for any reason, keep the previously stored value.
    }

    // Access token behaves like a password: preserve unless user provides a new one.
    if (!empty($existing['taler_token'])) {
        $sanitized['taler_token'] = $existing['taler_token'];
    }

    // ENCRYPT token before saving to DB (only when user provides a new value)
    if (array_key_exists('taler_token', $input) && $input['taler_token'] !== '') {
        $encrypted = taler_encrypt_str((string) $input['taler_token']);
        if ($encrypted !== '') {
            $sanitized['taler_token'] = $encrypted;
        }
        // If encryption fails for any reason, keep the previously stored value.
    }

    /**
     * Validation logic:
     * - Either BOTH Username + Password must be filled,
     * - OR Access Token must be filled.
     */
    $username_present = ($sanitized['ext_username'] !== '');

    $password_present =
        (!empty($existing['ext_password'])) ||
        (array_key_exists('ext_password', $input) && $input['ext_password'] !== '');

    $token_present =
        (!empty($existing['taler_token'])) ||
        (array_key_exists('taler_token', $input) && $input['taler_token'] !== '');

    $valid = $token_present || ($username_present && $password_present);

    if (!$valid) {
        add_settings_error(
            'taler_options',
            'taler_credentials_invalid',
            'Please provide either (1) both Username and Password, or (2) an Access Token.',
            'error'
        );

        // Prevent partially saving an invalid combination.
        return $existing;
    }
    
    return $sanitized;
}

function taler_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <?php settings_errors('taler_options'); ?>

        <div class="notice notice-info">
            <p>
                <strong>Security:</strong> Passwords and tokens are <strong>encrypted</strong> before database storage.
            </p>
        </div>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('taler_options'); // SECURITY: Nonces + capability checks
            do_settings_sections('taler');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}
