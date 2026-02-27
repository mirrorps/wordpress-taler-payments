<?php

namespace TalerPayments\Services;

interface SettingsNoticesInterface
{
    public function addOnce(string $setting, string $code, string $message, string $type = 'error'): void;
}
