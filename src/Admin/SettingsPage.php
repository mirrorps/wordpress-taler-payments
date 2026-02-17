<?php
namespace TalerPayments\Admin;

use TalerPayments\Settings\Options;
use TalerPayments\Settings\SettingsSaveService;

/**
 * Admin settings page wiring + rendering.
 */
final class SettingsPage
{
    public function __construct(
        private readonly SettingsSaveService $saveService,
    ) {
    }

    public function hooks(): void
    {
        add_action('admin_menu', [$this, 'addAdminPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addAdminPage(): void
    {
        add_options_page(
            __('Taler Payments Settings', 'taler-payments'),
            __('Taler Payments', 'taler-payments'),
            'manage_options',
            'taler-payments',
            [$this, 'render']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'settings_page_taler-payments') {
            return;
        }

        $plugin_root = dirname(__FILE__, 3);
        $plugin_file = $plugin_root . '/taler-payments.php';

        $css_path = $plugin_root . '/assets/taler-admin.css';
        $css_url  = plugin_dir_url($plugin_file) . 'assets/taler-admin.css';
        $ver      = @filemtime($css_path) ?: (defined('TALER_PAYMENTS_VERSION') ? TALER_PAYMENTS_VERSION : '1.0.0');

        wp_enqueue_style('taler-payments-admin', $css_url, [], $ver);
    }

    public function registerSettings(): void
    {
        // Register the option in each group so each form can submit independently.
        // IMPORTANT: only attach the sanitize callback once to avoid duplicate sanitization/notices.
        register_setting('taler_baseurl_group', Options::OPTION_NAME, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizeOptions'],
            'default'           => [],
        ]);

        // Keep the forms separate by registering settings groups, but store everything in one option array.
        register_setting('taler_userpass_group', Options::OPTION_NAME, [
            'type'              => 'array',
            'default'           => [],
        ]);

        register_setting('taler_token_group', Options::OPTION_NAME, [
            'type'              => 'array',
            'default'           => [],
        ]);
    }

    /**
     * @param mixed $input
     * @return array<string, mixed>
     */
    public function sanitizeOptions($input): array
    {
        // dd($_POST, $input);
        return $this->saveService->sanitizeForSave($input, $_POST, Options::get());
    }

    public function render(): void
    {
        $options = Options::get();

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

        $view = plugin_dir_path(__FILE__) . 'views/settings-page.php';
        if (is_readable($view)) {
            include $view;
            return;
        }

        echo '<div class="wrap"><h1>' . esc_html(get_admin_page_title()) . '</h1></div>';
    }
}

