<?php
namespace TalerPayments\Services\DTO;

/**
 * Typed payload for a backend login check attempt.
 */
final class LoginCheckContext
{
    public function __construct(
        public readonly TalerFactoryOptions $factoryOptions,
        public readonly string $authMethod,
    ) {
    }
}
