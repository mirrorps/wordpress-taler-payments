<?php
namespace TalerPayments\Services;

use TalerPayments\Helpers\Crypto;
use TalerPayments\Services\DTO\LoginCheckContext;
use TalerPayments\Services\DTO\TalerFactoryOptions;

/**
 * Centralizes auth normalization and SDK factory option building.
 */
final class MerchantAuthConfigurator
{
    public const MODE_AUTO = 'auto';
    public const MODE_TOKEN = 'token';
    public const MODE_USERPASS = 'userpass';
    public const AUTH_METHOD_TOKEN = 'token';
    public const AUTH_METHOD_USERPASS = 'userpass';
    private const USERPASS_SCOPE_CHECK = 'readonly';
    private const USERPASS_SCOPE_RUNTIME = 'order-full';
    private const TOKEN_DURATION_US = 3600_000_000;
    private const DESCRIPTION_CHECK = 'WordPress taler-payments settings check';
    private const DESCRIPTION_RUNTIME = 'WordPress taler-payments';

    /** @var \Closure(string):string */
    private readonly \Closure $decrypt;

    /**
     * @param null|callable(string):string $decrypt
     */
    public function __construct(?callable $decrypt = null)
    {
        $this->decrypt = \Closure::fromCallable($decrypt ?? [Crypto::class, 'decryptString']);
    }

    /**
     * Normalize auth token value for SDK (expects full Authorization header value).
     */
    public function normalizeAuthToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }
        if (!preg_match('/^(Bearer|Basic)\s+/i', $token)) {
            return 'Bearer ' . $token;
        }
        return $token;
    }

    /**
     * Build typed client factory options from saved plugin settings.
     *
     * @param array<string,mixed> $options
     */
    public function buildClientFactoryOptions(array $options): TalerFactoryOptions
    {
        $parsed = $this->parseSettings($options);
        $factoryOptions = TalerFactoryOptions::withBaseUrl($parsed['base_url']);

        // Access token has priority if both are configured.
        if ($parsed['token'] !== '') {
            return $factoryOptions->withToken($parsed['token']);
        }

        if ($this->hasUserPassCredentials($parsed)) {
            return $this->addUserPassAuth(
                $factoryOptions,
                $parsed,
                self::USERPASS_SCOPE_RUNTIME,
                self::DESCRIPTION_RUNTIME
            );
        }

        // No auth configured (SDK will still validate /config).
        return $factoryOptions->withToken('');
    }

    /**
     * Build login check options with selected auth method metadata.
     *
     * @param array<string,mixed> $options
     */
    public function buildLoginCheckContext(array $options, string $mode = self::MODE_AUTO): ?LoginCheckContext
    {
        if (!$this->isValidMode($mode)) {
            return null;
        }

        $parsed = $this->parseSettings($options);
        if ($parsed['base_url'] === '') {
            return null;
        }

        $factoryOptions = TalerFactoryOptions::withBaseUrl($parsed['base_url']);

        if ($mode === self::MODE_TOKEN) {
            if ($parsed['token'] === '') {
                return null;
            }

            return new LoginCheckContext(
                $factoryOptions->withToken($parsed['token']),
                self::AUTH_METHOD_TOKEN
            );
        }

        if ($mode === self::MODE_USERPASS) {
            if (!$this->hasUserPassCredentials($parsed)) {
                return null;
            }
            $factoryOptions = $this->addUserPassAuth(
                $factoryOptions,
                $parsed,
                self::USERPASS_SCOPE_CHECK,
                self::DESCRIPTION_CHECK
            );
            return new LoginCheckContext($factoryOptions, self::AUTH_METHOD_USERPASS);
        }

        // Auto mode: access token has priority if both are configured.
        if ($parsed['token'] !== '') {
            return new LoginCheckContext(
                $factoryOptions->withToken($parsed['token']),
                self::AUTH_METHOD_TOKEN
            );
        }

        if ($this->hasUserPassCredentials($parsed)) {
            $factoryOptions = $this->addUserPassAuth(
                $factoryOptions,
                $parsed,
                self::USERPASS_SCOPE_CHECK,
                self::DESCRIPTION_CHECK
            );
            return new LoginCheckContext($factoryOptions, self::AUTH_METHOD_USERPASS);
        }

        return null;
    }

    /**
     * @param array<string,mixed> $options
     * @return array{
     *   base_url: string,
     *   token: string,
     *   username: string,
     *   password: string,
     *   instance: string
     * }
     */
    private function parseSettings(array $options): array
    {
        $baseUrl = isset($options['taler_base_url']) ? trim((string) $options['taler_base_url']) : '';

        $tokenEnc = isset($options['taler_token']) ? (string) $options['taler_token'] : '';
        $token = $tokenEnc !== '' ? ($this->decrypt)($tokenEnc) : '';
        $token = $this->normalizeAuthToken($token);

        $username = isset($options['ext_username']) ? trim((string) $options['ext_username']) : '';
        $passwordEnc = isset($options['ext_password']) ? (string) $options['ext_password'] : '';
        $password = $passwordEnc !== '' ? ($this->decrypt)($passwordEnc) : '';
        $instance = isset($options['taler_instance']) ? trim((string) $options['taler_instance']) : '';

        return [
            'base_url' => $baseUrl,
            'token'    => $token,
            'username' => $username,
            'password' => $password,
            'instance' => $instance,
        ];
    }

    /**
     * @param array{
     *   base_url: string,
     *   token: string,
     *   username: string,
     *   password: string,
     *   instance: string
     * } $parsed
     */
    private function hasUserPassCredentials(array $parsed): bool
    {
        return $parsed['username'] !== ''
            && $parsed['password'] !== ''
            && $parsed['instance'] !== '';
    }

    /**
     * @param TalerFactoryOptions $factoryOptions
     * @param array{
     *   base_url: string,
     *   token: string,
     *   username: string,
     *   password: string,
     *   instance: string
     * } $parsed
     */
    private function addUserPassAuth(
        TalerFactoryOptions $factoryOptions,
        array $parsed,
        string $scope,
        string $description
    ): TalerFactoryOptions
    {
        return $factoryOptions->withUserPass(
            $parsed['username'],
            $parsed['password'],
            $parsed['instance'],
            $scope,
            self::TOKEN_DURATION_US,
            $description
        );
    }

    private function isValidMode(string $mode): bool
    {
        return $mode === self::MODE_AUTO
            || $mode === self::MODE_TOKEN
            || $mode === self::MODE_USERPASS;
    }
}
