<?php
namespace TalerPayments\Public\Security;

/**
 * WordPress-backed request security checks.
 */
final class WordPressRequestSecurity implements RequestSecurityInterface
{
    public function isPostRequest(): bool
    {
        $method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
        return $method === 'POST';
    }

    public function isValidNonce(string $action, string $nonce): bool
    {
        return wp_verify_nonce($nonce, $action) !== false;
    }
}
