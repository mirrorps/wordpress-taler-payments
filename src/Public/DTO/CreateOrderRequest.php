<?php
namespace TalerPayments\Public\DTO;

use TalerPayments\Public\Config\PublicDefaults;
use TalerPayments\Public\Input\InputInterface;
use TalerPayments\Public\Validation\AmountValidatorInterface;

/**
 * Validated payload for public order creation.
 */
final class CreateOrderRequest
{
    public function __construct(
        private readonly string $amount,
        private readonly string $summary,
    ) {
    }

    /**
     * Build and validate request payload from generic input.
     */
    public static function fromInput(InputInterface $input, AmountValidatorInterface $amountValidator): self
    {
        $amountInput = wp_unslash($input->get('amount', PublicDefaults::AMOUNT));
        $summaryInput = wp_unslash($input->get('summary', PublicDefaults::SUMMARY));

        $amount = $amountValidator->validateAmount((string) $amountInput);
        if ($amount === null) {
            throw new InvalidAmountException('invalid_amount');
        }

        $summary = sanitize_text_field((string) $summaryInput);
        if ($summary === '') {
            $summary = PublicDefaults::SUMMARY;
        }

        if (function_exists('mb_substr')) {
            $summary = mb_substr($summary, 0, PublicDefaults::MAX_SUMMARY_LENGTH, 'UTF-8');
        } else {
            $summary = substr($summary, 0, PublicDefaults::MAX_SUMMARY_LENGTH);
        }

        return new self($amount, $summary);
    }

    public function amount(): string
    {
        return $this->amount;
    }

    public function summary(): string
    {
        return $this->summary;
    }
}
