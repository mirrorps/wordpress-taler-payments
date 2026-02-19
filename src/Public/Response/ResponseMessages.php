<?php
namespace TalerPayments\Public\Response;

/**
 * Centralized messages for public AJAX responses.
 */
final class ResponseMessages
{
    public static function methodNotAllowed(): string
    {
        return __('Method not allowed.', 'taler-payments');
    }

    public static function tooManyRequests(): string
    {
        return __('Too many requests. Please try again shortly.', 'taler-payments');
    }

    public static function invalidAmountFormat(): string
    {
        return __('Invalid amount format.', 'taler-payments');
    }

    public static function invalidNonce(): string
    {
        return __('Invalid security token.', 'taler-payments');
    }

    public static function missingPayUri(): string
    {
        return __('Taler: order created but no pay URI available.', 'taler-payments');
    }

    public static function temporarilyUnavailable(): string
    {
        return __('Taler payment temporarily unavailable.', 'taler-payments');
    }

    public static function runtimeError(): string
    {
        return __('Taler runtime error.', 'taler-payments');
    }
}
