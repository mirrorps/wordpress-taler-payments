<?php
/**
 * Plugin Name: Taler Payments
 * Plugin URI: https://github.com/mirrorps/wordpress-taler-payments
 * Description: The Taler Payments plugin integrates the GNU Taler payment system, enabling seamless payments and donations on any WordPress site.
 * Version: 0.1.0
 * License: GPLv2 or later
 * Author: mirrorps
 */

if (!defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/vendor/autoload.php';

if (is_admin()) {
    add_action('plugins_loaded', static function (): void {
        static $booted = false;
        if ($booted) {
            return;
        }
        $booted = true;

        $notices = new \TalerPayments\Services\SettingsNotices();
        $checker = new \TalerPayments\Services\MerchantBackendChecker($notices);
        $sanitizer = new \TalerPayments\Settings\Sanitizer($notices, $checker);
        $settingsPage = new \TalerPayments\Admin\SettingsPage($sanitizer);
        $settingsPage->hooks();
    });
}

define('TALER_PAYMENTS_VERSION', '0.1.0');


use Taler\Api\Order\Dto\Amount;
use Taler\Api\Order\Dto\CheckPaymentUnpaidResponse;
use Taler\Api\Order\Dto\OrderV0;
use Taler\Api\Order\Dto\PostOrderRequest;

/**
 * Get the plugin Taler service.
 */
function taler_wp_taler_service(): \TalerPayments\Services\Taler
{
    static $service = null;
    if ($service === null) {
        $service = new \TalerPayments\Services\Taler();
    }
    return $service;
}

/**
 * Create a new order and return its taler:// pay URI.
 */
function taler_wp_create_order_pay_uri(string $amount, string $summary): ?string
{
    $orderClient = taler_wp_taler_service()
        ->client()
        ->order();

    $order = new OrderV0(
        summary: sanitize_text_field($summary),
        amount: new Amount(sanitize_text_field($amount)),
        fulfillment_message: 'Thank you for your purchase. Your order will be fulfilled after payment.'
    );

    $request = new PostOrderRequest(order: $order);

    // 1) Create order and get its ID
    $created = $orderClient->createOrder($request);

    // 2) Fetch unpaid order status, including taler_pay_uri
    $status = $orderClient->getOrder($created->order_id);

    if ($status instanceof CheckPaymentUnpaidResponse && $status->taler_pay_uri !== null) {
        return $status->taler_pay_uri;
    }

    return null;
}

function taler_wp_ajax_create_order(): void
{
    // Nonce must be provided as `_ajax_nonce`.
    check_ajax_referer('taler_wp_create_order');

    $amount  = isset($_REQUEST['amount']) ? sanitize_text_field(wp_unslash($_REQUEST['amount'])) : 'KUDOS:1.00';
    $summary = isset($_REQUEST['summary']) ? sanitize_text_field(wp_unslash($_REQUEST['summary'])) : 'Donation';

    try {
        $payUri = taler_wp_create_order_pay_uri($amount, $summary);

        if ($payUri === null) {
            wp_send_json_error(['message' => 'Taler: order created but no pay URI available.'], 502);
        }

        wp_send_json_success(['taler_pay_uri' => $payUri]);
    } catch (\Taler\Exception\TalerException $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            wp_send_json_error(['message' => $e->getMessage()], 502);
        }
        wp_send_json_error(['message' => 'Taler payment temporarily unavailable.'], 502);
    } catch (\Throwable $e) {
        wp_send_json_error(['message' => 'Taler runtime error.'], 500);
    }
}

add_action('wp_ajax_taler_wp_create_order', 'taler_wp_ajax_create_order');
add_action('wp_ajax_nopriv_taler_wp_create_order', 'taler_wp_ajax_create_order');

/**
 * Track whether the shortcode was used on the page (so we only output one modal).
 */
function taler_wp_mark_shortcode_used(): void
{
    $GLOBALS['taler_wp_shortcode_used'] = true;
}

function taler_wp_is_shortcode_used(): bool
{
    return !empty($GLOBALS['taler_wp_shortcode_used']);
}

function taler_wp_enqueue_assets(): void
{
    $base = plugin_dir_url(__FILE__) . 'assets/';
    
    /**
     * TODO: remove mtimes as versions after testing
     */
    $assets_dir = __DIR__ . '/assets/';
    // Use file mtimes as versions to avoid stale browser/plugin caches during development.
    $css_ver = @filemtime($assets_dir . 'taler-payments.css') ?: TALER_PAYMENTS_VERSION;
    $qrcode_ver = @filemtime($assets_dir . 'davidshimjs-qrcodejs-04f46c6/qrcode.min.js') ?: TALER_PAYMENTS_VERSION;
    $js_ver = @filemtime($assets_dir . 'taler-payments.js') ?: TALER_PAYMENTS_VERSION;

    wp_enqueue_style(
        'taler-payments',
        $base . 'taler-payments.css',
        [],
        $css_ver
    );

    // QR generator
    wp_enqueue_script(
        'taler-payments-qrcode',
        $base . 'davidshimjs-qrcodejs-04f46c6/qrcode.min.js',
        [],
        $qrcode_ver,
        true
    );

    wp_enqueue_script(
        'taler-payments',
        $base . 'taler-payments.js',
        ['taler-payments-qrcode'],
        $js_ver,
        true
    );

    wp_localize_script('taler-payments', 'TalerPayments', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('taler_wp_create_order'),
        'walletInfoUrl' => 'https://www.taler.net/en/wallet.html',
        'qrCodeLibUrl' => $base . 'davidshimjs-qrcodejs-04f46c6/qrcode.min.js?ver=' . rawurlencode((string)$qrcode_ver),
        'i18n' => [
            'title' => 'GNU Taler Payment',
            'creatingOrder' => 'Preparing your payment…',
            'payInBrowser' => 'Pay with Taler wallet in the browser',
            'walletInstallText' => 'To pay in the browser, the Taler Wallet extension must be installed.',
            'walletInstallLinkText' => 'Get the wallet',
            'qrHelp' => 'Or scan this QR code with your mobile wallet:',
            'errorGeneric' => 'Payment temporarily unavailable. Please try again.',
            'close' => 'Close',
        ],
    ]);
}

/**
 * Shortcode: [taler_pay_button amount="EUR:5.00" summary="Donation"]
 *
 * Renders a "Pay with GNU Taler" link. The order is only created after the user clicks.
 *
 * IMPORTANT: The currency MUST be supported by the Taler Exchange!
 */
function taler_wp_render_pay_button($atts): string
{
    $atts = shortcode_atts(
        [
            'amount'  => 'KUDOS:1.00',
            'summary' => 'Donation',
        ],
        $atts,
        'taler_pay_button'
    );

    $amount  = sanitize_text_field($atts['amount']);
    $summary = sanitize_text_field($atts['summary']);

    taler_wp_mark_shortcode_used();
    taler_wp_enqueue_assets();

    return sprintf(
        '<a href="#" class="taler-pay-button" data-taler-amount="%s" data-taler-summary="%s" role="button" aria-haspopup="dialog">Pay with Taler</a>',
        esc_attr($amount),
        esc_attr($summary)
    );
}

add_shortcode('taler_pay_button', 'taler_wp_render_pay_button');

function taler_wp_render_modal_once(): void
{
    static $rendered = false;
    if ($rendered || !taler_wp_is_shortcode_used()) {
        return;
    }
    $rendered = true;

    ?>
    <div class="taler-modal" id="taler-payments-modal" aria-hidden="true">
        <div class="taler-modal__overlay" data-taler-close></div>
        <div class="taler-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="taler-modal-title">
            <button type="button" class="taler-modal__close" data-taler-close aria-label="<?php echo esc_attr__('Close', 'taler-payments'); ?>">×</button>
            <h2 class="taler-modal__title" id="taler-modal-title">GNU Taler Payment</h2>

            <div class="taler-modal__summary">
                <div class="taler-modal__row">
                    <div class="taler-modal__label">Amount</div>
                    <div class="taler-modal__value" id="taler-modal-amount">—</div>
                </div>
                <div class="taler-modal__row">
                    <div class="taler-modal__label">Summary</div>
                    <div class="taler-modal__value" id="taler-modal-summary">—</div>
                </div>
            </div>

            <div class="taler-modal__status" id="taler-modal-status"></div>
            <div class="taler-modal__error" id="taler-modal-error" role="alert" aria-live="polite"></div>

            <div class="taler-modal__actions">
                <a class="taler-modal__primary" id="taler-modal-pay-btn" href="#" rel="noreferrer" target="_blank">Pay with Taler wallet in the browser</a>
                <div class="taler-modal__help">
                    <div id="taler-modal-wallet-help">
                        To pay in the browser, the GNU Taler Wallet extension must be installed.
                        <a id="taler-modal-wallet-link" href="https://www.taler.net/en/wallet.html" target="_blank" rel="noreferrer">Get the wallet</a>
                    </div>
                </div>
            </div>

            <div class="taler-modal__qr">
                <div class="taler-modal__qr-label" id="taler-modal-qr-help">Or scan this QR code with your mobile wallet:</div>
                <div class="taler-modal__qr-box" id="taler-modal-qr"></div>
            </div>
        </div>
    </div>
    <?php
}

add_action('wp_footer', 'taler_wp_render_modal_once', 20);

/**
 * Add GNU Taler support meta tag in the document head for browsers to recognize the protocol and Taler wallet extension.
 * This is necessary for the browser to open the taler:// URI in the taler wallet extension.
 */
function taler_wp_add_taler_support_meta_tag(): void
{
    // Hint to the Taler Wallet extension that this page wants taler:// support.
    // (Some versions use this content to decide which integration features to enable.)
    echo "<meta name=\"taler-support\" content=\"uri,api,hijack\">\n";
}

add_action('wp_head', 'taler_wp_add_taler_support_meta_tag');

/**
 * Allow the "taler" protocol in the content.
 */
add_filter('kses_allowed_protocols', function( $protocols ){
    if (!in_array( 'taler', $protocols, true )){
        $protocols[] = 'taler';
    }
    return $protocols;
});