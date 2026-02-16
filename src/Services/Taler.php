<?php
namespace TalerPayments\Services;

use Taler\Factory\Factory;
use TalerPayments\Services\DTO\TalerFactoryOptions;
use TalerPayments\Settings\Options;

/**
 * Taler SDK client factory.
 */
final class Taler
{
    private ?\Taler\Taler $client = null;
    private ?TalerFactoryOptions $factoryOptions;

    /** @var \Closure():array<string,mixed> */
    private readonly \Closure $optionsGetter;

    private readonly MerchantAuthConfigurator $authConfigurator;

    /**
     * @param null|array<string,mixed> $factoryOptions
     * @param null|callable():array<string,mixed> $optionsGetter
     */
    public function __construct(
        ?array $factoryOptions = null,
        ?callable $optionsGetter = null,
        ?MerchantAuthConfigurator $authConfigurator = null,
    ) {
        $this->factoryOptions = $factoryOptions !== null ? TalerFactoryOptions::fromArray($factoryOptions) : null;
        $this->optionsGetter = \Closure::fromCallable($optionsGetter ?? [Options::class, 'get']);
        $this->authConfigurator = $authConfigurator ?? new MerchantAuthConfigurator();
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

        if ($this->factoryOptions !== null) {
            $this->client = $this->createClient($this->factoryOptions);
            return $this->client;
        }

        $options = ($this->optionsGetter)();
        if (!is_array($options)) {
            $options = [];
        }

        $factoryOptions = $this->authConfigurator->buildClientFactoryOptions($options);
        $this->client = $this->createClient($factoryOptions);
        return $this->client;
    }

    public function createClient(TalerFactoryOptions $factoryOptions): \Taler\Taler
    {
        return Factory::create($factoryOptions->toArray());
    }
}

