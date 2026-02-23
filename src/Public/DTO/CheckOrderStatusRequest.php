<?php
namespace TalerPayments\Public\DTO;

use TalerPayments\Public\Input\InputInterface;

/**
 * Validated payload for public order status checks.
 */
final class CheckOrderStatusRequest
{
    public function __construct(private readonly string $orderId)
    {
    }

    public static function fromInput(InputInterface $input): self
    {
        $orderIdInput = wp_unslash($input->get('order_id', ''));
        $orderId = sanitize_text_field((string) $orderIdInput);

        if ($orderId === '') {
            throw new InvalidOrderIdException('invalid_order_id');
        }

        if (function_exists('mb_substr')) {
            $orderId = mb_substr($orderId, 0, 128, 'UTF-8');
        } else {
            $orderId = substr($orderId, 0, 128);
        }

        if ($orderId === '') {
            throw new InvalidOrderIdException('invalid_order_id');
        }

        return new self($orderId);
    }

    public function orderId(): string
    {
        return $this->orderId;
    }
}
