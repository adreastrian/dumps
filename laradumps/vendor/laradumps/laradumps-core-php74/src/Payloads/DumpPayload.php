<?php

namespace LaraDumps\LaraDumpsCore\Payloads;

use LaraDumps\LaraDumpsCore\Actions\ConvertArrayToPhpSyntax;

class DumpPayload extends Payload
{
    /** @var mixed */
    public $dump;

    /** @var mixed */
    public $originalContent;

    /** @var string|null */
    public $variableType;

    /** @var string */
    private $screen;

    /** @var string */
    private $label;

    public function __construct(
        $dump,
        $originalContent = null,
        $variableType = null,
        $screen = 'home',
        $label = ''
    ) {
        $this->dump = $dump;
        $this->originalContent = $originalContent;
        $this->variableType = $variableType;
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
            'dump'             => $this->dump,
            'original_content' => ConvertArrayToPhpSyntax::convert($this->originalContent),
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
