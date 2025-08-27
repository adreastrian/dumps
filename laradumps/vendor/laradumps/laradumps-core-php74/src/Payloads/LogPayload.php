<?php

namespace LaraDumps\LaraDumpsCore\Payloads;

class LogPayload extends Payload
{
    private array $value = [];
    public function __construct(
         array $value
    ) {
        $this->value = $value;
    }

    public function type(): string
    {
        return 'log_application';
    }

    public function content(): array
    {
        return $this->value;
    }

    public function toScreen()
    {
        return new Screen('logs');
    }

    public function withLabel()
    {
        return [];
    }
}