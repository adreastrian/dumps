<?php

namespace LaraDumps\LaraDumpsCore\Payloads;

class TimeTrackPayload extends Payload
{
    /**
     * Clock script execution time
     */
        /** @var string */
    public $reference;

    /** @var  */
    public $stop;

    /** @var string */
    public $screen;

    public function __construct(
        $reference,
        $stop= false,
        $screen = 'home'
    ) {
        $this->reference = $reference;
        $this->stop = $stop;
        $this->screen = $screen;
    }

    public function type(): string
    {
        return 'time_track';
    }

    /** @return array<string, mixed> */
    public function content(): array
    {
        $content = [
            'tracker_id' => uniqid(),
            'time'       => microtime(true),
        ];

        if ($this->stop) {
            $content['end_time'] = microtime(true);
        }

        return $content;
    }

    public function toScreen(): array{
        return new Screen($this->screen);
    }

    public function withLabel(): array{
        return new Label($this->reference);
    }
}
