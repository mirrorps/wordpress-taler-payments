<?php

declare(strict_types=1);

namespace TalerPayments\Public\Response;

final class WordPressResponseSpyState
{
    public static ?array $successPayload = null;
    public static ?array $errorPayload = null;
    public static ?int $errorStatus = null;
    /** @var list<array{message:string,status:int}> */
    public static array $errors = [];
    /** @var list<string> */
    public static array $logs = [];

    public static function reset(): void
    {
        self::$successPayload = null;
        self::$errorPayload = null;
        self::$errorStatus = null;
        self::$errors = [];
        self::$logs = [];
    }
}

if (!function_exists(__NAMESPACE__ . '\\__')) {
    function __(string $text, string $domain): string
    {
        return $text;
    }
}

if (!function_exists(__NAMESPACE__ . '\\wp_send_json_success')) {
    function wp_send_json_success(array $data): void
    {
        WordPressResponseSpyState::$successPayload = $data;
    }
}

if (!function_exists(__NAMESPACE__ . '\\wp_send_json_error')) {
    function wp_send_json_error(array $data, int $status): void
    {
        WordPressResponseSpyState::$errorPayload = $data;
        WordPressResponseSpyState::$errorStatus = $status;
        WordPressResponseSpyState::$errors[] = [
            'message' => (string) ($data['message'] ?? ''),
            'status' => $status,
        ];
    }
}

if (!function_exists(__NAMESPACE__ . '\\error_log')) {
    function error_log(string $message): bool
    {
        WordPressResponseSpyState::$logs[] = $message;

        return true;
    }
}
