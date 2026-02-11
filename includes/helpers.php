<?php

/**
 * Shared helper functions for taler-payments.
 *
 * NOTE: This file is intended to be loaded for both admin and frontend requests.
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Encrypt a string for storage using libsodium secretbox.
 *
 * Returns a base64 string containing nonce+ciphertext, or an empty string on failure.
 */
if (!function_exists('taler_encrypt_str')) {
	function taler_encrypt_str(string $plaintext): string {
		if ($plaintext === '') {
			return '';
		}

		if (!function_exists('sodium_crypto_secretbox')) {
			return '';
		}

		$key_material =
			(defined('AUTH_KEY') ? AUTH_KEY : '') .
			(defined('NONCE_KEY') ? NONCE_KEY : '') .
			(defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY : '');

		// Fallback to WP salts if constants are unavailable for some reason.
		if ($key_material === '' && function_exists('wp_salt')) {
			$key_material = wp_salt('auth');
		}

		try {
			$key = substr(hash('sha256', $key_material, true), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
			$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
			$encrypted = sodium_crypto_secretbox($plaintext, $nonce, $key);
		} catch (\Throwable $e) {
			return '';
		}

		return base64_encode($nonce . $encrypted);
	}
}

/**
 * Decrypt a string previously encrypted with taler_encrypt_str().
 *
 * Returns the decrypted string, or an empty string on failure.
 */
if (!function_exists('taler_decrypt_str')) {
	function taler_decrypt_str(string $encrypted_data): string {
		if ($encrypted_data === '') {
			return '';
		}

		if (!function_exists('sodium_crypto_secretbox_open')) {
			return '';
		}

		$key_material =
			(defined('AUTH_KEY') ? AUTH_KEY : '') .
			(defined('NONCE_KEY') ? NONCE_KEY : '') .
			(defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY : '');

		if ($key_material === '' && function_exists('wp_salt')) {
			$key_material = wp_salt('auth');
		}

		$key = substr(hash('sha256', $key_material, true), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

		$data = base64_decode($encrypted_data, true);
		if ($data === false) {
			return '';
		}

		if (strlen($data) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
			return '';
		}

		$nonce = mb_substr($data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
		$ciphertext = mb_substr($data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

		try {
			$decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
		} catch (\Throwable $e) {
			return '';
		}
		return $decrypted !== false ? $decrypted : '';
	}
}

