<?php

namespace LaraDumps\LaraDumpsCore\Payloads;

use LaraDumps\LaraDumpsCore\Actions\ConvertArrayToPhpSyntax;

class HtmlPayload extends Payload
{
    /** @var mixed */
    public $html;

    /** @var string|null */
    public $variableType;

    /** @var string */
    private $screen;

    /** @var string */
    private $label;

    public function __construct(
        $html,
        $screen = 'home',
        $label = ''
    ) {
        $this->html = $html;
        $this->variableType = 'html';
        $this->screen = $screen;
        $this->label = $label;
    }

    public function type(): string
    {
        return 'dump';
    }

    public function content(): array
    {
        return [
            'dump'             => $this->html,
            'original_content' => $this->html,
            'variable_type'    => $this->variableType,
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
