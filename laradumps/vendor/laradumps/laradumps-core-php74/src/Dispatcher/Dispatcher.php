<?php

namespace LaraDumps\LaraDumpsCore\Dispatcher;

use LaraDumps\LaraDumpsCore\Actions\Config;
use LaraDumps\LaraDumpsCore\Support\CodeSnippet;

class Dispatcher
{
    public function handle(array $payload): bool
    {
        $dispatcher = new Curl();
        return $dispatcher->handle($payload);
    }
}
