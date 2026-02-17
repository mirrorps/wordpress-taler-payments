<?php
namespace TalerPayments\Settings\DTO;

final class SanitizeResult
{
    /**
     * @param array<string,mixed> $options
     */
    private function __construct(
        private readonly array $options,
        private readonly ?string $loginCheckMode,
    ) {
    }

    /**
     * @param array<string,mixed> $options
     */
    public static function withLoginCheck(array $options, string $mode): self
    {
        return new self($options, $mode);
    }

    /**
     * @param array<string,mixed> $options
     */
    public static function withoutLoginCheck(array $options): self
    {
        return new self($options, null);
    }

    /**
     * @return array<string,mixed>
     */
    public function options(): array
    {
        return $this->options;
    }

    public function loginCheckMode(): ?string
    {
        return $this->loginCheckMode;
    }
}
