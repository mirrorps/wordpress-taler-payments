<?php
namespace TalerPayments\Public;

use Taler\Api\Order\Dto\Amount;
use Taler\Api\Order\Dto\CheckPaymentUnpaidResponse;
use Taler\Api\Order\Dto\OrderV0;
use Taler\Api\Order\Dto\PostOrderRequest;
use TalerPayments\Services\MerchantAuthConfigurator;
use TalerPayments\Services\Taler;
use TalerPayments\Settings\Options;

/**
 * Handles public order creation logic and request-level protections.
 */
final class OrderService
{
    public function __construct(
        private readonly Taler $talerService = new Taler(),
        private readonly MerchantAuthConfigurator $authConfigurator = new MerchantAuthConfigurator(),
    ) {
    }

    /**
     * Detect auth failures returned by the Merchant backend.
     */
    public function isUnauthorized(\Throwable $e): bool
    {
        return (int) $e->getCode() === 401;
    }

    /**
     * Create a new order and return its taler:// pay URI.
     */
    public function createOrderPayUri(string $amount, string $summary): ?string
    {
        try {
            return $this->createOrderPayUriWithClient($this->talerService->client(), $amount, $summary);
        } catch (\Taler\Exception\TalerException $e) {
            if (!$this->isUnauthorized($e)) {
                throw $e;
            }

            // If token auth fails (stale/rotated token), retry once with configured user/pass runtime auth.
            $fallbackUri = $this->createOrderPayUriWithUserpassFallback($amount, $summary);
            if ($fallbackUri !== null) {
                return $fallbackUri;
            }

            throw $e;
        }
    }

    /**
     * Basic public endpoint throttling for order creation.
     */
    public function allowOrderCreateRequest(): bool
    {
        $window = (int) apply_filters('taler_wp_rate_limit_window_seconds', 60);
        $limit = (int) apply_filters('taler_wp_rate_limit_max_requests', 15);

        if ($window < 1 || $limit < 1) {
            return true;
        }

        $ip = '';
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        $userPart = is_user_logged_in() ? 'u:' . get_current_user_id() : 'g:' . $ip;
        $key = 'taler_wp_rl_' . md5($userPart);
        $hits = (int) get_transient($key);

        if ($hits >= $limit) {
            return false;
        }

        set_transient($key, $hits + 1, $window);
        return true;
    }

    private function createOrderPayUriWithUserpassFallback(string $amount, string $summary): ?string
    {
        $options = Options::get();
        $factoryOptions = $this->authConfigurator->buildRuntimeUserPassFactoryOptions($options);
        if ($factoryOptions === null) {
            return null;
        }

        $service = new Taler($factoryOptions->toArray());
        return $this->createOrderPayUriWithClient($service->client(), $amount, $summary);
    }

    private function createOrderPayUriWithClient(\Taler\Taler $client, string $amount, string $summary): ?string
    {
        $orderClient = $client->order();

        $order = new OrderV0(
            summary: sanitize_text_field($summary),
            amount: new Amount(sanitize_text_field($amount)),
            fulfillment_message: 'Thank you for your purchase. Your order will be fulfilled after payment.'
        );

        $request = new PostOrderRequest(order: $order);

        // 1) Create order and get its ID.
        $created = $orderClient->createOrder($request);

        // 2) Fetch unpaid order status, including taler_pay_uri.
        $status = $orderClient->getOrder($created->order_id);

        if ($status instanceof CheckPaymentUnpaidResponse && $status->taler_pay_uri !== null) {
            return $status->taler_pay_uri;
        }

        return null;
    }
}
