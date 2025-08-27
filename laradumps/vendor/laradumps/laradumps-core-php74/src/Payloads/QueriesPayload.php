<?php

namespace LaraDumps\LaraDumpsCore\Payloads;

class QueriesPayload extends Payload
{
    private array $queries = [];
    private string $screen = 'queries';
    private string $label = '';
    public function __construct(
        $queries = [],
        $screen = 'queries',
        $label = ''
    ) {
        $this->queries = $queries;
        $this->screen = $screen;
        $this->label = $label;
    }

    public function type(): string
    {
        return 'queries';
    }

    public function content(): array
    {
        return $this->queries;
    }

    public function toScreen()
    {
        return new Screen($this->screen);
    }

    public function withLabel()
    {
        return new Label($this->label);
    }
}