<?php

namespace TalerPayments\Helpers;

/**
 * Crypto helpers for encrypting/decrypting secrets stored in WordPress options.
 *
 * Uses libsodium secretbox with a key derived from WP salts.
 */
final class Crypto
{
    /**
     * Encrypt a string for storage using libsodium secretbox.
     *
     * Returns a base64 string containing nonce+ciphertext, or an empty string on failure.
     */
    public static function encryptString(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }

        if (!\function_exists('sodium_crypto_secretbox')) {
            return '';
        }

        $keyMaterial =
            (\defined('AUTH_KEY') ? AUTH_KEY : '') .
            (\defined('NONCE_KEY') ? NONCE_KEY : '') .
            (\defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY : '');

        // Fallback to WP salts if constants are unavailable for some reason.
        if ($keyMaterial === '' && \function_exists('wp_salt')) {
            $keyMaterial = \wp_salt('auth');
        }

        try {
            $key = \substr(\hash('sha256', $keyMaterial, true), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
            $nonce = \random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $encrypted = \sodium_crypto_secretbox($plaintext, $nonce, $key);
        } catch (\Throwable $e) {
            return '';
        }

        return \base64_encode($nonce . $encrypted);
    }

    /**
     * Decrypt a string previously encrypted with encryptString().
     *
     * Returns the decrypted string, or an empty string on failure.
     */
    public static function decryptString(string $encryptedData): string
    {
        if ($encryptedData === '') {
            return '';
        }

        if (!\function_exists('sodium_crypto_secretbox_open')) {
            return '';
        }

        $keyMaterial =
            (\defined('AUTH_KEY') ? AUTH_KEY : '') .
            (\defined('NONCE_KEY') ? NONCE_KEY : '') .
            (\defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY : '');

        if ($keyMaterial === '' && \function_exists('wp_salt')) {
            $keyMaterial = \wp_salt('auth');
        }

        $key = \substr(\hash('sha256', $keyMaterial, true), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

        $data = \base64_decode($encryptedData, true);
        if ($data === false) {
            return '';
        }

        if (\strlen($data) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }

        $nonce = \mb_substr($data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = \mb_substr($data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

        try {
            $decrypted = \sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        } catch (\Throwable $e) {
            return '';
        }

        return $decrypted !== false ? $decrypted : '';
    }
}

