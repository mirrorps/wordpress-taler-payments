<?php
namespace TalerPayments\Bootstrap;

use TalerPayments\Public\AjaxOrderController;
use TalerPayments\Public\Input\ArrayInput;
use TalerPayments\Public\OrderService;
use TalerPayments\Public\PublicPresentation;
use TalerPayments\Public\Response\JsonResponder;
use TalerPayments\Public\Security\WordPressRequestSecurity;
use TalerPayments\Public\Validation\AmountValidator;
use TalerPayments\Services\MerchantAuthConfigurator;
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
            new ArrayInput($_POST),
            new WordPressRequestSecurity(),
            new JsonResponder()
        );
    }

    public function createPresentation(string $pluginBaseUrl, string $pluginBasePath): PublicPresentation
    {
        return new PublicPresentation($pluginBaseUrl, $pluginBasePath);
    }
}
