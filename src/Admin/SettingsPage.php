<?php
namespace TalerPayments\Admin;

use TalerPayments\Public\Config\PublicUiTexts;
use TalerPayments\Settings\Options;
use TalerPayments\Settings\SettingsSaveService;

if (!defined('ABSPATH')){
    exit;
}

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
            __('MirrorPS Payments for GNU Taler Settings', 'mirrorps-payments-for-gnu-taler'),
            __('MirrorPS Payments for GNU Taler', 'mirrorps-payments-for-gnu-taler'),
            'manage_options',
            'mirrorps-payments-for-gnu-taler',
            [$this, 'render']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'settings_page_mirrorps-payments-for-gnu-taler') {
            return;
        }

        $plugin_root = dirname(__FILE__, 3);
        $plugin_file = $plugin_root . '/mirrorps-payments-for-gnu-taler.php';

        $css_path = $plugin_root . '/css/taler-admin.css';
        $css_url  = plugin_dir_url($plugin_file) . 'css/taler-admin.css';

        $mtime = @filemtime($css_path);
        if ($mtime !== false && $mtime > 0) {
            $ver = (string) $mtime;
        } elseif (defined('TALER_PAYMENTS_VERSION')) {
            $ver = TALER_PAYMENTS_VERSION;
        } else {
            $ver = '1.0.0';
        }

        wp_enqueue_style('mirrorps-gnu-taler-payments-admin', $css_url, [], $ver);
    }

    public function registerSettings(): void
    {
        // Register the option in each group so each form can submit independently.
        $optionArgs = [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizeOptions'],
            'default'           => [],
        ];
        register_setting('taler_baseurl_group', Options::OPTION_NAME, $optionArgs);
        register_setting('taler_userpass_group', Options::OPTION_NAME, $optionArgs);
        register_setting('taler_token_group', Options::OPTION_NAME, $optionArgs);
        register_setting('taler_public_texts_group', Options::OPTION_NAME, $optionArgs);
    }

    /**
     * @param mixed $input
     * @return array<string, mixed>
     */
    public function sanitizeOptions($input): array
    {
        // dd($_POST, $input);
        return $this->saveService->sanitizeForSave($input, filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW), Options::get());
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

        $thank_you_message = isset($options[PublicUiTexts::OPTION_THANK_YOU_MESSAGE]) ? (string) $options[PublicUiTexts::OPTION_THANK_YOU_MESSAGE] : '';
        $pay_button_cta = isset($options[PublicUiTexts::OPTION_PAY_BUTTON_CTA]) ? (string) $options[PublicUiTexts::OPTION_PAY_BUTTON_CTA] : '';
        $check_status_button_text = isset($options[PublicUiTexts::OPTION_CHECK_STATUS_BUTTON]) ? (string) $options[PublicUiTexts::OPTION_CHECK_STATUS_BUTTON] : '';
        $check_status_hint = isset($options[PublicUiTexts::OPTION_CHECK_STATUS_HINT]) ? (string) $options[PublicUiTexts::OPTION_CHECK_STATUS_HINT] : '';
        $has_public_text_overrides = trim($thank_you_message) !== ''
            || trim($pay_button_cta) !== ''
            || trim($check_status_button_text) !== ''
            || trim($check_status_hint) !== '';

        $delete_confirm = __('Deleting credentials is irreversible. Are you sure you want to continue?', 'mirrorps-payments-for-gnu-taler');
        $reset_public_texts_confirm = __('Reset public text customization to defaults? This will remove all custom text values.', 'mirrorps-payments-for-gnu-taler');

        $view = plugin_dir_path(__FILE__) . 'views/settings-page.php';
        if (is_readable($view)) {
            include $view;
            return;
        }

        echo '<div class="wrap"><h1>' . esc_html(get_admin_page_title()) . '</h1></div>';
    }
}

