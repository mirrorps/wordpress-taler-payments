<?php
namespace TalerPayments\Public\DTO;

/**
 * Value object with newly created order details.
 */
final class OrderCreationResult
{
    public function __construct(
        private readonly string $orderId,
        private readonly string $talerPayUri,
    ) {
    }

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function talerPayUri(): string
    {
        return $this->talerPayUri;
    }
}
