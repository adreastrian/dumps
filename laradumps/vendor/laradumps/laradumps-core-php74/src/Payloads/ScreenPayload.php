<?php

namespace LaraDumps\LaraDumpsCore\Payloads;

class ScreenPayload extends Payload
{
        /** @var string */
    public $name;

    /** @var  */
    public $raiseIn;

    /** @var bool */
    public $newWindow;

    public function __construct(
        $name,
        $raiseIn= 0,
        $newWindow = false
    ) {
        $this->name = $name;
        $this->raiseIn = $raiseIn;
        $this->newWindow = $newWindow;
    }

    public function type(): string
    {
        return 'screen';
    }

    public function content(): array
    {
        return [];
    }

    public function toScreen(){
        return new Screen($this->name, $this->raiseIn, $this->newWindow);
    }

    public function withLabel(): array{
        return [];
    }
}
