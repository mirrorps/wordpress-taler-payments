<?php
namespace TalerPayments\Public;

use TalerPayments\Public\Config\PublicDefaults;

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
        $base = $this->pluginBaseUrl . 'assets/';
        $assetsDir = $this->pluginBasePath . '/assets/';
        $cssVersion = $this->assetVersion($assetsDir . 'taler-payments.css');
        $qrcodeVersion = $this->assetVersion($assetsDir . 'davidshimjs-qrcodejs-04f46c6/qrcode.min.js');
        $jsVersion = $this->assetVersion($assetsDir . 'taler-payments.js');

        wp_enqueue_style(
            'taler-payments',
            $base . 'taler-payments.css',
            [],
            $cssVersion
        );

        // QR generator.
        wp_enqueue_script(
            'taler-payments-qrcode',
            $base . 'davidshimjs-qrcodejs-04f46c6/qrcode.min.js',
            [],
            $qrcodeVersion,
            true
        );

        wp_enqueue_script(
            'taler-payments',
            $base . 'taler-payments.js',
            ['taler-payments-qrcode'],
            $jsVersion,
            true
        );

        wp_localize_script('taler-payments', 'TalerPayments', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('taler_wp_create_order'),
            'walletInfoUrl' => 'https://www.taler.net/en/wallet.html',
            'qrCodeLibUrl' => $base . 'davidshimjs-qrcodejs-04f46c6/qrcode.min.js?ver=' . rawurlencode((string) $qrcodeVersion),
            'defaults' => [
                'amount' => PublicDefaults::AMOUNT,
                'summary' => PublicDefaults::SUMMARY,
            ],
            'i18n' => [
                'title' => __('GNU Taler Payment', 'taler-payments'),
                'creatingOrder' => __('Preparing your payment…', 'taler-payments'),
                'payInBrowser' => __('Pay with Taler wallet in the browser', 'taler-payments'),
                'walletInstallText' => __('To pay in the browser, the Taler Wallet extension must be installed.', 'taler-payments'),
                'walletInstallLinkText' => __('Get the wallet', 'taler-payments'),
                'qrHelp' => __('Or scan this QR code with your mobile wallet:', 'taler-payments'),
                'qrUnavailable' => __('QR generator not available.', 'taler-payments'),
                'errorGeneric' => __('Payment temporarily unavailable. Please try again.', 'taler-payments'),
                'close' => __('Close', 'taler-payments'),
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

        ?>
        <div class="taler-modal" id="taler-payments-modal" aria-hidden="true">
            <div class="taler-modal__overlay" data-taler-close></div>
            <div class="taler-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="taler-modal-title">
                <button type="button" class="taler-modal__close" data-taler-close aria-label="<?php echo esc_attr__('Close', 'taler-payments'); ?>">×</button>
                <h2 class="taler-modal__title" id="taler-modal-title"><?php echo esc_html__('GNU Taler Payment', 'taler-payments'); ?></h2>

                <div class="taler-modal__summary">
                    <div class="taler-modal__row">
                        <div class="taler-modal__label"><?php echo esc_html__('Amount', 'taler-payments'); ?></div>
                        <div class="taler-modal__value" id="taler-modal-amount">—</div>
                    </div>
                    <div class="taler-modal__row">
                        <div class="taler-modal__label"><?php echo esc_html__('Summary', 'taler-payments'); ?></div>
                        <div class="taler-modal__value" id="taler-modal-summary">—</div>
                    </div>
                </div>

                <div class="taler-modal__status" id="taler-modal-status"></div>
                <div class="taler-modal__error" id="taler-modal-error" role="alert" aria-live="polite"></div>

                <div class="taler-modal__actions">
                    <a class="taler-modal__primary" id="taler-modal-pay-btn" href="#" rel="noreferrer" target="_blank"><?php echo esc_html__('Pay with Taler wallet in the browser', 'taler-payments'); ?></a>
                    <div class="taler-modal__help">
                        <div id="taler-modal-wallet-help">
                            <?php echo esc_html__('To pay in the browser, the GNU Taler Wallet extension must be installed.', 'taler-payments'); ?>
                            <a id="taler-modal-wallet-link" href="https://www.taler.net/en/wallet.html" target="_blank" rel="noreferrer"><?php echo esc_html__('Get the wallet', 'taler-payments'); ?></a>
                        </div>
                    </div>
                </div>

                <div class="taler-modal__qr">
                    <div class="taler-modal__qr-label" id="taler-modal-qr-help"><?php echo esc_html__('Or scan this QR code with your mobile wallet:', 'taler-payments'); ?></div>
                    <div class="taler-modal__qr-box" id="taler-modal-qr"></div>
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
