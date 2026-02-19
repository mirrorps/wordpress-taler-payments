<?php
namespace TalerPayments\Public\Security;

/**
 * Security checks for public AJAX requests.
 */
interface RequestSecurityInterface
{
    public function isPostRequest(): bool;

    public function isValidNonce(string $action, string $nonce): bool;
}
