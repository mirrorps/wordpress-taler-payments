<?php
namespace TalerPayments\Bootstrap;

use TalerPayments\Public\AjaxOrderController;

/**
 * Registers public-facing plugin wiring.
 */
final class PublicBootstrap
{
    private ?AjaxOrderController $ajaxController = null;
    private readonly PublicWiringFactory $wiringFactory;

    public function __construct(
        private readonly string $pluginBaseUrl,
        private readonly string $pluginBasePath,
        ?PublicWiringFactory $wiringFactory = null,
    ) {
        $this->wiringFactory = $wiringFactory ?? new PublicWiringFactory();
    }

    public function boot(): void
    {
        add_action('wp_ajax_taler_wp_create_order', [$this, 'handleAjaxCreateOrder']);
        add_action('wp_ajax_nopriv_taler_wp_create_order', [$this, 'handleAjaxCreateOrder']);

        if (is_admin()) {
            return;
        }

        $presentation = $this->wiringFactory->createPresentation(
            $this->pluginBaseUrl,
            $this->pluginBasePath
        );

        add_shortcode('taler_pay_button', [$presentation, 'renderPayButton']);
        add_action('wp_footer', [$presentation, 'renderModalOnce'], 20);
        add_action('wp_head', [$presentation, 'addTalerSupportMetaTag']);
    }

    public function handleAjaxCreateOrder(): void
    {
        $this->ajaxController()->handle();
    }

    private function ajaxController(): AjaxOrderController
    {
        if ($this->ajaxController !== null) {
            return $this->ajaxController;
        }

        $this->ajaxController = $this->wiringFactory->createAjaxOrderController();

        return $this->ajaxController;
    }
}
