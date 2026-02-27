<?php

namespace TalerPayments\Services;

use TalerPayments\Services\DTO\TalerFactoryOptions;

interface TalerClientFactoryInterface
{
    public function createClient(TalerFactoryOptions $factoryOptions): \Taler\Taler;
}
