<?php

namespace LaraDumps\LaraDumpsCore\Dispatcher;

use LaraDumps\LaraDumpsCore\Payloads\Payload;

interface PayloadSenderInterface
{
    /**
     * @param array|Payload $payload
     */
    public function handle($payload): bool;
}
