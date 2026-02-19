<?php
namespace TalerPayments\Public\Response;

/**
 * Small helper for consistent JSON responses.
 */
final class JsonResponder
{
    public function success(array $data): void
    {
        wp_send_json_success($data);
    }

    public function error(string $message, int $status): void
    {
        wp_send_json_error(['message' => $message], $status);
    }

    public function debugLog(string $prefix, \Throwable $e): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($prefix . ': ' . $e->getMessage() . ' (code: ' . $e->getCode() . ')');
        }
    }
}
