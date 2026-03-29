<?php
namespace TalerPayments\Public;

use TalerPayments\Public\Config\PublicDefaults;
use TalerPayments\Public\Config\PublicUiTexts;
use TalerPayments\Settings\Options;

/**
 * Handles public shortcode rendering, assets, and wallet support hints.
 */
final class PublicPresentation
{
    private bool $shortcodeUsed = false;
    private bool $modalRendered = false;

    public function __construct(
        private readonly string $pluginBaseUrl,
        private readonly string $pluginBasePath,
    ) {
    }

    /**
     * Track whether the shortcode was used on the page.
     */
    public function markShortcodeUsed(): void
    {
        $this->shortcodeUsed = true;
    }

    public function isShortcodeUsed(): bool
    {
        return $this->shortcodeUsed;
    }

    /**
     * Detect shortcode usage in the currently queried singular post/page.
     */
    public function currentViewHasTalerShortcode(): bool
    {
        if (!is_singular()) {
            return false;
        }

        $postId = get_queried_object_id();
        if (!is_int($postId) || $postId <= 0) {
            return false;
        }

        $content = get_post_field('post_content', $postId);
        if (!is_string($content) || $content === '') {
            return false;
        }

        return has_shortcode($content, 'taler_pay_button');
    }

    /**
     * Decide if current request should enable public Taler UI/protocol hints.
     */
    public function shouldEnablePublicSupport(): bool
    {
        if (is_admin()) {
            return false;
        }

        return $this->isShortcodeUsed() || $this->currentViewHasTalerShortcode();
    }

    public function enqueueAssets(): void
    {
        $publicTextOptions = PublicUiTexts::resolve(Options::get());

        $cssBase = $this->pluginBaseUrl . 'css/';
        $jsBase = $this->pluginBaseUrl . 'js/';

        $cssDir = $this->pluginBasePath . '/css/';
        $jsDir = $this->pluginBasePath . '/js/';

        $cssVersion = $this->assetVersion($cssDir . 'taler-payments.css');
        $qrcodeVersion = $this->assetVersion($jsDir . 'davidshimjs-qrcodejs-04f46c6/qrcode.min.js');
        $jsVersion = $this->assetVersion($jsDir . 'taler-payments.js');

        wp_enqueue_style(
            'mirrorps-gnu-taler-payments',
            $cssBase . 'taler-payments.css',
            [],
            $cssVersion
        );

        // QR generator.
        wp_enqueue_script(
            'mirrorps-gnu-taler-payments-qrcode',
            $jsBase . 'davidshimjs-qrcodejs-04f46c6/qrcode.min.js',
            [],
            $qrcodeVersion,
            true
        );

        wp_enqueue_script(
            'mirrorps-gnu-taler-payments',
            $jsBase . 'taler-payments.js',
            ['mirrorps-gnu-taler-payments-qrcode'],
            $jsVersion,
            true
        );

        wp_localize_script('mirrorps-gnu-taler-payments', 'TalerPayments', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('taler_wp_create_order'),
            'nonceCreateOrder' => wp_create_nonce('taler_wp_create_order'),
            'nonceCheckOrderStatus' => wp_create_nonce('taler_wp_check_order_status'),
            'walletInfoUrl' => 'https://www.taler.net/en/wallet.html',
            'qrCodeLibUrl' => $jsBase . 'davidshimjs-qrcodejs-04f46c6/qrcode.min.js?ver=' . rawurlencode((string) $qrcodeVersion),
            'defaults' => [
                'amount' => PublicDefaults::AMOUNT,
                'summary' => PublicDefaults::SUMMARY,
            ],
            'i18n' => [
                'title' => __('Taler Payment', 'mirrorps-payments-for-gnu-taler'),
                'creatingOrder' => __('Preparing your payment ...', 'mirrorps-payments-for-gnu-taler'),
                'payInBrowser' => $publicTextOptions[PublicUiTexts::OPTION_PAY_BUTTON_CTA],
                'walletInstallText' => __('To pay in the browser, the Taler Wallet extension must be installed.', 'mirrorps-payments-for-gnu-taler'),
                'walletInstallLinkText' => __('Get the wallet', 'mirrorps-payments-for-gnu-taler'),
                'qrHelp' => __('Or scan this QR code with your mobile wallet:', 'mirrorps-payments-for-gnu-taler'),
                'checkPaymentStatus' => $publicTextOptions[PublicUiTexts::OPTION_CHECK_STATUS_BUTTON],
                'checkPaymentStatusHelp' => $publicTextOptions[PublicUiTexts::OPTION_CHECK_STATUS_HINT],
                'checkingPaymentStatus' => __('Checking payment status...', 'mirrorps-payments-for-gnu-taler'),
                'paymentCompleted' => $publicTextOptions[PublicUiTexts::OPTION_THANK_YOU_MESSAGE],
                'paymentNotYetCompleted' => __('Payment not confirmed yet. Please complete payment and try again.', 'mirrorps-payments-for-gnu-taler'),
                'paymentStatusUnavailable' => __('Order is still being prepared. Please try checking again in a moment.', 'mirrorps-payments-for-gnu-taler'),
                'qrUnavailable' => __('QR generator not available.', 'mirrorps-payments-for-gnu-taler'),
                'errorGeneric' => __('Payment temporarily unavailable. Please try again.', 'mirrorps-payments-for-gnu-taler'),
                'close' => __('Close', 'mirrorps-payments-for-gnu-taler'),
            ],
        ]);
    }

    /**
     * Shortcode: [taler_pay_button amount="EUR:5.00" summary="Donation"]
     */
    public function renderPayButton(mixed $atts): string
    {
        $atts = shortcode_atts(
            [
                'amount'  => PublicDefaults::AMOUNT,
                'summary' => PublicDefaults::SUMMARY,
                'text'    => 'Pay with Taler',
            ],
            $atts,
            'taler_pay_button'
        );

        $amount = sanitize_text_field($atts['amount']);
        $summary = sanitize_text_field($atts['summary']);
        $text = sanitize_text_field($atts['text']);

        $this->markShortcodeUsed();
        $this->enqueueAssets();

        return sprintf(
            '<button type="button" class="taler-pay-button" data-taler-amount="%s" data-taler-summary="%s" aria-haspopup="dialog" aria-controls="taler-payments-modal">%s</button>',
            esc_attr($amount),
            esc_attr($summary),
            esc_html($text)
        );
    }

    public function renderModalOnce(): void
    {
        if ($this->modalRendered || !$this->isShortcodeUsed()) {
            return;
        }
        $this->modalRendered = true;
        $publicTextOptions = PublicUiTexts::resolve(Options::get());

        ?>
        <div class="taler-modal" id="taler-payments-modal" aria-hidden="true">
            <div class="taler-modal__overlay" data-taler-close></div>
            <div class="taler-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="taler-modal-title">
                <button type="button" class="taler-modal__close" data-taler-close aria-label="<?php echo esc_attr__('Close', 'mirrorps-payments-for-gnu-taler'); ?>">×</button>
                <h2 class="taler-modal__title" id="taler-modal-title"><?php echo esc_html__('GNU Taler Payment', 'mirrorps-payments-for-gnu-taler'); ?></h2>

                <div class="taler-modal__summary">
                    <div class="taler-modal__row">
                        <div class="taler-modal__label"><?php echo esc_html__('Amount', 'mirrorps-payments-for-gnu-taler'); ?></div>
                        <div class="taler-modal__value" id="taler-modal-amount">—</div>
                    </div>
                    <div class="taler-modal__row">
                        <div class="taler-modal__label"><?php echo esc_html__('Summary', 'mirrorps-payments-for-gnu-taler'); ?></div>
                        <div class="taler-modal__value" id="taler-modal-summary">—</div>
                    </div>
                </div>

                <div class="taler-modal__status" id="taler-modal-status"></div>
                <div class="taler-modal__error" id="taler-modal-error" role="alert" aria-live="polite"></div>

                <div class="taler-modal__actions">
                    <a class="taler-modal__primary" id="taler-modal-pay-btn" href="#" rel="noreferrer" target="_blank"><?php echo esc_html($publicTextOptions[PublicUiTexts::OPTION_PAY_BUTTON_CTA]); ?></a>
                    <div class="taler-modal__help">
                        <div id="taler-modal-wallet-help">
                            <?php echo esc_html__('To pay in the browser, the GNU Taler Wallet extension must be installed.', 'mirrorps-payments-for-gnu-taler'); ?>
                            <a id="taler-modal-wallet-link" href="https://www.taler.net/en/wallet.html" target="_blank" rel="noreferrer"><?php echo esc_html__('Get the wallet', 'mirrorps-payments-for-gnu-taler'); ?></a>
                        </div>
                    </div>
                </div>

                <div class="taler-modal__qr" style="text-align: center">
                    <div class="taler-modal__qr-label" id="taler-modal-qr-help"><?php echo esc_html__('Or scan this QR code with your mobile wallet:', 'mirrorps-payments-for-gnu-taler'); ?></div>
                    <div class="taler-modal__qr-box" id="taler-modal-qr"></div>
                </div>

                <div class="taler-modal__status-actions">
                    <div class="taler-modal__help" id="taler-modal-check-status-help">
                        <?php echo esc_html($publicTextOptions[PublicUiTexts::OPTION_CHECK_STATUS_HINT]); ?>
                    </div>
                    <div class="taler-modal__check-status-message" id="taler-modal-check-status-message" role="status" aria-live="polite"></div>
                    <button type="button" class="taler-modal__secondary" id="taler-modal-check-status-btn"><?php echo esc_html($publicTextOptions[PublicUiTexts::OPTION_CHECK_STATUS_BUTTON]); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    public function addTalerSupportMetaTag(): void
    {
        if (!$this->shouldEnablePublicSupport()) {
            return;
        }

        // Hint to the Taler Wallet extension that this page wants taler:// support.
        // (Some versions use this content to decide which integration features to enable.)
        echo "<meta name=\"taler-support\" content=\"uri,api,hijack\">\n";
    }

    private function assetVersion(string $assetPath): string
    {
        if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG && is_readable($assetPath)) {
            $mtime = filemtime($assetPath);
            if (is_int($mtime) && $mtime > 0) {
                return (string) $mtime;
            }
        }

        return TALER_PAYMENTS_VERSION;
    }
}
