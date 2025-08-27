<?php

namespace LaraDumps\LaraDumpsCore\Payloads;

use LaraDumps\LaraDumpsCore\Actions\Dumper;

class TableV2Payload extends Payload
{
        /** @var array */
    protected $values;

    /** @var  */
    protected $headerStyle;

    /** @var string */
    protected $screen;

    /** @var string */
    protected $label;

    public function __construct(
        $values,
        $headerStyle= '',
        $screen = 'home',
        $label = 'Table'
    ) {
        $this->values = $values;
        $this->headerStyle = $headerStyle;
        $this->screen = $screen;
        $this->label = $label;
    }

    public function type(): string
    {
        return 'table_v2';
    }

    public function content(): array
    {
        $values = array_map(function ($value) {
            return Dumper::dump($value);
        }, $this->values);

        return [
            'values'      => $values,
            'headerStyle' => $this->headerStyle,
        ];
    }

    public function toScreen(): array{
        return new Screen($this->screen);
    }

    public function withLabel(): array{
        return new Label($this->label);
    }
}
