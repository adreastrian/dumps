<?php

namespace LaraDumps\LaraDumpsCore\Payloads;

class LabelPayload extends Payload
{
    /**
     * ColorPayload constructor.
     */
        /** @var string */
    public $label;

    public function __construct(
        $label
    ) {
        $this->label = $label;
    }

    public function type(): string
    {
        return 'label';
    }

    public function content(): array
    {
        return [];
    }

    public function toScreen(): array{
        return [];
    }

    public function withLabel(){
        return new Label($this->label);
    }
}
