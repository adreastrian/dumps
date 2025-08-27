<?php

namespace LaraDumps\LaraDumpsCore\Payloads;

class Label
{
    /** @var string */
    public $label;

    public function __construct(
        $label = 'dump'
    ) {
        $this->label = $label;
    }
}
