<?php
/**
 * Taler Payments - Admin Settings for External System Credentials (ENCRYPTED)
 * INTEGRATES seamlessly with existing taler-payments plugin
 */

// SECURITY: Only load in admin
// if (!is_admin()) return;

// === ENCRYPTION FUNCTIONS ===
// Generate encryption key from WordPress AUTH_KEY (secure, site-unique)
function taler_get_encryption_key() {
    $raw_key = hash('sha256', AUTH_KEY, true);
    return sodium_pad(mb_substr($raw_key, 0, 32, '8bit'), 32);
}

// Encrypt password before DB storage
function taler_encrypt_password($password) {
    if (empty($password) || !function_exists('sodium_crypto_secretbox')) return '';
    
    $key = taler_get_encryption_key();
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    
    $encrypted = sodium_crypto_secretbox($password, $nonce, $key);
    if ($encrypted === false) return '';
    
    // Store nonce + encrypted data (base64 for DB safety)
    return base64_encode($nonce . $encrypted);
}

// Decrypt password when needed (for Taler API calls)
function taler_decrypt_password($encrypted_data) {
    if (empty($encrypted_data) || !function_exists('sodium_crypto_secretbox_open')) return '';
    
    $key = taler_get_encryption_key();
    $data = base64_decode($encrypted_data);
    
    if (strlen($data) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return '';
    
    $nonce = mb_substr($data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
    $encrypted = mb_substr($data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
    
    $decrypted = sodium_crypto_secretbox_open($encrypted, $nonce, $key);
    return $decrypted !== false ? $decrypted : '';
}

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
    $value = !empty($options['ext_password']) ? '••••••••' : '';
    echo '<input type="password" name="taler_options[ext_password]" value="' . 
         esc_attr($value) . 
         '" class="regular-text ltr" autocomplete="new-password" />';
    echo '<p class="description">Password will be <strong>encrypted</strong> before storage using Sodium Crypto.</p>';
}

function taler_sanitize($input) {
    $sanitized = [];
    $sanitized['ext_username'] = sanitize_text_field($input['ext_username'] ?? '');
    
    // ENCRYPT password before saving to DB
    if (!empty($input['ext_password'])) {
        $sanitized['ext_password'] = taler_encrypt_password($input['ext_password']);
    }
    
    return $sanitized;
}

function taler_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="notice notice-info">
            <p><strong>Security:</strong> Passwords are <strong>encrypted</strong> using Sodium Crypto (PHP 7.2+) before database storage. 
            Decryption key derived from site AUTH_KEY. Full WordPress Settings API security included.</p>
        </div>
        
        <form method="post" action="options.php">
            <?php
            settings_fields('taler_options'); // SECURITY: Nonces + capability checks
            do_settings_sections('taler');
            submit_button();
            ?>
        </form>
        
        <h3>How to use in your existing taler-payments code:</h3>
        <pre style="background:#f1f1f1;padding:15px;font-size:12px;">
// Retrieve Taler credentials anywhere in your plugin
$creds = get_option('taler_options');
$username = $creds['ext_username'] ?? '';
$password = taler_decrypt_password($creds['ext_password'] ?? '');

// Use for Taler API calls
// curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
        </pre>
    </div>
    <?php
}
