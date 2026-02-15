<?php
namespace TalerPayments\Services;

use Taler\Factory\Factory;
use TalerPayments\Helpers\Crypto;
use TalerPayments\Settings\Options;

/**
 * Taler SDK client factory.
 */
final class Taler
{
    private ?\Taler\Taler $client = null;
    
    /** @var \Closure():array<string,mixed> */
    private readonly \Closure $optionsGetter;
    
    /** @var \Closure(string):string */
    private readonly \Closure $decrypt;

    /**
     * @param null|array<string,mixed> $factoryOptions
     * @param null|callable():array<string,mixed> $optionsGetter
     * @param null|callable(string):string $decrypt
     */
    public function __construct(
        private readonly ?array $factoryOptions = null,
        ?callable $optionsGetter = null,
        ?callable $decrypt = null,
    ) {
        $this->optionsGetter = \Closure::fromCallable($optionsGetter ?? [Options::class, 'get']);
        $this->decrypt = \Closure::fromCallable($decrypt ?? [Crypto::class, 'decryptString']);
    }

    /**
     * Create a Taler client from factory options.
     *
     * @param array<string,mixed> $factoryOptions
     * @return \Taler\Taler
     */
    public static function create(array $factoryOptions): \Taler\Taler
    {
        return (new self($factoryOptions))->client();
    }

    /**
     * Drop cached client instance (useful in tests).
     */
    public function clearClientCache(): void
    {
        $this->client = null;
    }

    /**
     * Lazily create and reuse a Taler client.
     */
    public function client(): \Taler\Taler
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if($this->factoryOptions) {
            $this->client = Factory::create($this->factoryOptions);
            return $this->client;
        }

        $options = ($this->optionsGetter)();
        if (!is_array($options)) {
            $options = [];
        }

        $baseUrl = isset($options['taler_base_url']) ? trim((string) $options['taler_base_url']) : '';

        $tokenEnc = isset($options['taler_token']) ? (string) $options['taler_token'] : '';
        $token = $tokenEnc !== '' ? ($this->decrypt)($tokenEnc) : '';
        $token = self::normalizeAuthToken($token);

        $factoryOptions = [
            'base_url' => $baseUrl,
        ];

        // Access token has priority if both are configured.
        if ($token !== '') {
            $factoryOptions['token'] = $token;
        } else {
            $username = isset($options['ext_username']) ? trim((string) $options['ext_username']) : '';
            $passwordEnc = isset($options['ext_password']) ? (string) $options['ext_password'] : '';
            $password = $passwordEnc !== '' ? ($this->decrypt)($passwordEnc) : '';
            $instance = isset($options['taler_instance']) ? trim((string) $options['taler_instance']) : '';

            if ($username !== '' && $password !== '' && $instance !== '') {
                $factoryOptions['username'] = $username;
                $factoryOptions['password'] = $password;
                $factoryOptions['instance'] = $instance;
                // Request a token scope suitable for order creation/lookup.
                $factoryOptions['scope'] = 'order-full';
                $factoryOptions['duration_us'] = 3600_000_000;
                $factoryOptions['description'] = 'WordPress taler-payments';
            } else {
                // No auth configured (SDK will still validate /config).
                $factoryOptions['token'] = '';
            }
        }

        $this->client = Factory::create($factoryOptions);
        return $this->client;
    }

    /**
     * Normalize auth token value for SDK (expects full Authorization header value).
     */
    private static function normalizeAuthToken(string $token): string
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
}

