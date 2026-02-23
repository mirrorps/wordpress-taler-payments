<?php
namespace TalerPayments\Public;

use Taler\Api\Order\Dto\Amount;
use Taler\Api\Order\Dto\CheckPaymentClaimedResponse;
use Taler\Api\Order\Dto\CheckPaymentPaidResponse;
use Taler\Api\Order\Dto\CheckPaymentUnpaidResponse;
use Taler\Api\Order\Dto\OrderV0;
use Taler\Api\Order\Dto\PostOrderRequest;
use TalerPayments\Public\DTO\OrderCreationResult;
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
        $created = $this->createOrder($amount, $summary);
        
        return $created?->talerPayUri();
    }

    /**
     * Create a new order and return details needed by the frontend.
     */
    public function createOrder(string $amount, string $summary): ?OrderCreationResult
    {
        try {
            return $this->createOrderWithClient($this->talerService->client(), $amount, $summary);
        } catch (\Taler\Exception\TalerException $e) {
            if (!$this->isUnauthorized($e)) {
                throw $e;
            }

            // If token auth fails (stale/rotated token), retry once with configured user/pass runtime auth.
            $fallbackResult = $this->createOrderWithUserpassFallback($amount, $summary);
            if ($fallbackResult !== null) {
                return $fallbackResult;
            }

            throw $e;
        }
    }

    /**
     * Basic public endpoint throttling for order creation.
     */
    public function allowOrderCreateRequest(): bool
    {
        return $this->allowRequest(
            'create',
            (int) apply_filters('taler_wp_rate_limit_window_seconds', 60),
            (int) apply_filters('taler_wp_rate_limit_max_requests', 15)
        );
    }

    /**
     * Basic public endpoint throttling for payment status checks.
     */
    public function allowOrderStatusRequest(): bool
    {
        return $this->allowRequest(
            'status',
            (int) apply_filters('taler_wp_status_rate_limit_window_seconds', 60),
            (int) apply_filters('taler_wp_status_rate_limit_max_requests', 30)
        );
    }

    /**
     * Query Merchant backend order status and return if payment is completed.
     */
    public function isOrderPaid(string $orderId): bool
    {
        try {
            return $this->isOrderPaidWithClient($this->talerService->client(), $orderId);
        } catch (\Taler\Exception\TalerException $e) {
            if (!$this->isUnauthorized($e)) {
                throw $e;
            }

            $fallbackPaid = $this->isOrderPaidWithUserpassFallback($orderId);
            if ($fallbackPaid !== null) {
                return $fallbackPaid;
            }

            throw $e;
        }
    }

    private function allowRequest(string $suffix, int $window, int $limit): bool
    {
        if ($window < 1 || $limit < 1) {
            return true;
        }

        $ip = '';
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        $userPart = is_user_logged_in() ? 'u:' . get_current_user_id() : 'g:' . $ip;
        $key = 'taler_wp_rl_' . $suffix . '_' . md5($userPart);
        $hits = (int) get_transient($key);

        if ($hits >= $limit) {
            return false;
        }

        set_transient($key, $hits + 1, $window);
        return true;
    }

    private function createOrderWithUserpassFallback(string $amount, string $summary): ?OrderCreationResult
    {
        $options = Options::get();
        $factoryOptions = $this->authConfigurator->buildRuntimeUserPassFactoryOptions($options);
        if ($factoryOptions === null) {
            return null;
        }

        $service = new Taler($factoryOptions->toArray());
        return $this->createOrderWithClient($service->client(), $amount, $summary);
    }

    private function createOrderWithClient(\Taler\Taler $client, string $amount, string $summary): ?OrderCreationResult
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
            return new OrderCreationResult(
                (string) $created->order_id,
                $status->taler_pay_uri
            );
        }

        return null;
    }

    private function isOrderPaidWithUserpassFallback(string $orderId): ?bool
    {
        $options = Options::get();
        $factoryOptions = $this->authConfigurator->buildRuntimeUserPassFactoryOptions($options);
        if ($factoryOptions === null) {
            return null;
        }

        $service = new Taler($factoryOptions->toArray());
        return $this->isOrderPaidWithClient($service->client(), $orderId);
    }

    private function isOrderPaidWithClient(\Taler\Taler $client, string $orderId): bool
    {
        $status = $client->order()->getOrder(sanitize_text_field($orderId));
        
        return match (true) {
            $status instanceof CheckPaymentPaidResponse => true,
            $status instanceof CheckPaymentUnpaidResponse,
            $status instanceof CheckPaymentClaimedResponse => false,
            default => false,
        };
    }
}
