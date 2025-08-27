<?php

namespace LaraDumps\LaraDumpsCore\Payloads;

class ColorPayload extends Payload
{
    /** @var string */
    public $color;

    /** @var string */
    private $screen;

    /** @var string */
    private $label;

    public function __construct(
        $color,
        $screen = 'home',
        $label = ''
    ) {
        $this->color = $color;
        $this->screen = $screen;
        $this->label = $label;
    }

    public function type(): string
    {
        return 'color';
    }

    /** @return array<string> */
    public function content(): array
    {
        return [
            'color' => $this->color,
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
