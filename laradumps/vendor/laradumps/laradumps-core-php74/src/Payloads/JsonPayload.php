<?php

namespace LaraDumps\LaraDumpsCore\Payloads;

class JsonPayload extends Payload
{
    /** @var string */
    public $string;

    /** @var string */
    private $screen;

    /** @var string */
    private $label;

    public function __construct(
        $string,
        $screen = 'home',
        $label = ''
    ) {
        $this->string = $string;
        $this->screen = $screen;
        $this->label = $label;
    }

    public function type(): string
    {
        return 'json';
    }

    public function content(): array
    {
        return [
            'string'           => $this->string,
            'original_content' => $this->string,
        ];
    }

    /**
     * @return Screen
     */
    public function toScreen()
    {
        return new Screen($this->screen);
    }

    /**
     * @return Label
     */
    public function withLabel()
    {
        return new Label($this->label);
    }
}
