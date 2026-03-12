<?php
namespace TalerPayments\Bootstrap;

use TalerPayments\Public\AjaxOrderController;
use TalerPayments\Public\AjaxOrderStatusController;
use TalerPayments\Public\Input\ArrayInput;
use TalerPayments\Public\PublicPresentation;
use TalerPayments\Public\Response\JsonResponder;
use TalerPayments\Public\Security\WordPressRequestSecurity;
use TalerPayments\Public\Validation\AmountValidator;
use TalerPayments\Services\MerchantAuthConfigurator;
use TalerPayments\Services\OrderService;
use TalerPayments\Services\Taler;

/**
 * Creates public runtime wiring objects.
 */
final class PublicWiringFactory
{
    public function createAjaxOrderController(): AjaxOrderController
    {
        $orderService = new OrderService(
            new Taler(),
            new MerchantAuthConfigurator()
        );

        return new AjaxOrderController(
            $orderService,
            new AmountValidator(),
            new ArrayInput(filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW) ?? []),
            new WordPressRequestSecurity(),
            new JsonResponder()
        );
    }

    public function createAjaxOrderStatusController(): AjaxOrderStatusController
    {
        $orderService = new OrderService(
            new Taler(),
            new MerchantAuthConfigurator()
        );

        return new AjaxOrderStatusController(
            $orderService,
            new ArrayInput(filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW) ?? []),
            new WordPressRequestSecurity(),
            new JsonResponder()
        );
    }

    public function createPresentation(string $pluginBaseUrl, string $pluginBasePath): PublicPresentation
    {
        return new PublicPresentation($pluginBaseUrl, $pluginBasePath);
    }
}
