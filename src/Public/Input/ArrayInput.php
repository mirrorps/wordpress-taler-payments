<?php
namespace TalerPayments\Public\Input;

/**
 * Array-backed input adapter (e.g. for $_POST or tests).
 */
final class ArrayInput implements InputInterface
{
    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        private readonly array $data,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }
}
