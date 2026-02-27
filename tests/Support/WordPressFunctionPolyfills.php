<?php

declare(strict_types=1);

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $text): string
    {
        return trim(strip_tags($text));
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook_name, mixed $value): mixed
    {
        return $value;
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain): string
    {
        return $text;
    }
}
