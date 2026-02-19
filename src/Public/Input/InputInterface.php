<?php
namespace TalerPayments\Public\Input;

/**
 * Generic input reader abstraction for request payloads.
 */
interface InputInterface
{
    public function get(string $key, mixed $default = null): mixed;
}
