<?php
namespace TalerPayments\Public\Response;

/**
 * Centralized messages for public AJAX responses.
 */
final class ResponseMessages
{
    public static function methodNotAllowed(): string
    {
        return __('Method not allowed.', 'mirrorps-payments-for-gnu-taler');
    }

    public static function tooManyRequests(): string
    {
        return __('Too many requests. Please try again shortly.', 'mirrorps-payments-for-gnu-taler');
    }

    public static function invalidAmountFormat(): string
    {
        return __('Invalid amount format.', 'mirrorps-payments-for-gnu-taler');
    }

    public static function invalidNonce(): string
    {
        return __('Invalid security token.', 'mirrorps-payments-for-gnu-taler');
    }

    public static function invalidOrderId(): string
    {
        return __('Invalid order identifier.', 'mirrorps-payments-for-gnu-taler');
    }

    public static function missingPayUri(): string
    {
        return __('Taler: order created but no pay URI available.', 'mirrorps-payments-for-gnu-taler');
    }

    public static function temporarilyUnavailable(): string
    {
        return __('Taler payment temporarily unavailable.', 'mirrorps-payments-for-gnu-taler');
    }

    public static function runtimeError(): string
    {
        return __('Taler runtime error.', 'mirrorps-payments-for-gnu-taler');
    }
}
