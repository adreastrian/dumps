<?php

namespace LaraDumps\LaraDumpsCore\Payloads;

class Screen
{
    /** @var string */
    public $screen_name;

    /** @var int */
    public $raise_in;

    /** @var bool */
    public $new_window;

    public function __construct(
        $screen_name = 'home',
        $raise_in = 0,
        $new_window = false
    ) {
        $this->screen_name = $screen_name;
        $this->raise_in = $raise_in;
        $this->new_window = $new_window;
    }
}
