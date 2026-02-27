<?php

namespace TalerPayments\Services;

use TalerPayments\Public\DTO\OrderCreationResult;

/**
 * Public order service contract used by AJAX controllers.
 */
interface OrderServiceInterface
{
    public function createOrder(string $amount, string $summary): ?OrderCreationResult;

    public function allowOrderCreateRequest(): bool;

    public function allowOrderStatusRequest(): bool;

    public function isOrderPaid(string $orderId): bool;
}
