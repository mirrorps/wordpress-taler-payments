<?php
namespace TalerPayments\Settings\DTO;

final class SanitizeContext
{
    public function __construct(
        private readonly string $optionPage,
        private readonly bool $isDelete,
    ) {
    }

    public function optionPage(): string
    {
        return $this->optionPage;
    }

    public function isDelete(): bool
    {
        return $this->isDelete;
    }
}
