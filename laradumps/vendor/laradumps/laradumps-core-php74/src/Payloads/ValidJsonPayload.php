<?php

namespace LaraDumps\LaraDumpsCore\Payloads;

class ValidJsonPayload extends Payload
{
    public function type(): string
    {
        return 'json_validate';
    }

    public function toScreen(): array{
        return [];
    }

    public function withLabel(): array{
        return [];
    }

    public function content(): array
    {
        return [];
    }
}
