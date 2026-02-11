<?php

// SECURITY: Only load in admin
 if (!is_admin()) return;


/**
 * Retrieve Taler credentials anywhere:
 *
$creds = get_option('taler_options');
$username = $creds['ext_username'] ?? '';
$password = taler_decrypt_password($creds['ext_password'] ?? '');
var_dump($username, $password);exit;
 *
*/

// $creds = get_option('taler_options');
// $username = $creds['ext_username'] ?? '';
// $password = taler_decrypt_password($creds['ext_password'] ?? '');
// var_dump($username, $password);
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
    add_settings_section('taler_main_section', 'Taler External System Credentials', null, 'taler');
    add_settings_field('ext_username', 'External Username', 'taler_username_cb', 'taler', 'taler_main_section');
    add_settings_field('ext_password', 'External Password', 'taler_password_cb', 'taler', 'taler_main_section');
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
    $value = taler_decrypt_password((string) $encrypted);
    echo '<input type="password" name="taler_options[ext_password]" value="' . 
         esc_attr($value) . 
         '" class="regular-text ltr" autocomplete="new-password" />';
    echo '<p class="description">Password will be <strong>encrypted</strong> before storage.</p>';
}

function taler_sanitize($input) {
    $sanitized = [];
    $sanitized['ext_username'] = sanitize_text_field($input['ext_username'] ?? '');
    
    // Preserve existing password unless a new one is provided.
    $existing = get_option('taler_options');
    if (!empty($existing['ext_password'])) {
        $sanitized['ext_password'] = $existing['ext_password'];
    }

    // ENCRYPT password before saving to DB (only when user provides a new value)
    if (array_key_exists('ext_password', $input) && $input['ext_password'] !== '') {
        $encrypted = taler_encrypt_password((string) $input['ext_password']);
        if ($encrypted !== '') {
            $sanitized['ext_password'] = $encrypted;
        }
        // If encryption fails for any reason, keep the previously stored value.
    }
    
    return $sanitized;
}

function taler_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <div class="notice notice-info">
            <p>
                <strong>Security:</strong> Passwords are <strong>encrypted</strong> before database storage.
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
