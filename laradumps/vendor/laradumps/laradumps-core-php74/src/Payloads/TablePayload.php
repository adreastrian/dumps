<?php

namespace LaraDumps\LaraDumpsCore\Payloads;

use LaraDumps\LaraDumpsCore\Actions\Table;

class TablePayload extends Payload
{
    /** @var iterable|object */
    private $data;

    /** @var string */
    private $name;

    /** @var string */
    protected $screen;

    /** @var string */
    protected $label;

    public function __construct(
        $data = [],
        $name = '',
        $screen = 'home',
        $label = 'Table'
    ) {
        $this->data = $data;
        $this->name = $name;
        $this->screen = $screen;
        $this->label = $label;

        if (empty($this->name)) {
            $this->name = 'Table';
        }
    }

    public function type(): string
    {
        return 'table';
    }

    public function content(): array
    {
        return Table::make($this->data, $this->name);
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
