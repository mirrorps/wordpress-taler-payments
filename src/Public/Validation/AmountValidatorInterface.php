<?php
namespace TalerPayments\Public\Validation;

/**
 * Validates and normalizes Taler amount values.
 */
interface AmountValidatorInterface
{
    public function validateAmount(string $raw): ?string;
}
