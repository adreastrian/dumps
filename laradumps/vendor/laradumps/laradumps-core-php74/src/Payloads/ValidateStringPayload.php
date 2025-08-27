<?php

namespace LaraDumps\LaraDumpsCore\Payloads;

class ValidateStringPayload extends Payload
{
        /** @var string */
    protected $content;

        /** @var bool */
    protected $caseSensitive= false;

        /** @var bool */
    protected $wholeWord= false;

        /** @var string */
    private $type;

    /** @var  */
    private $screen;

    /** @var string */
    private $label;

    public function __construct(
        $type,
        $screen= 'home',
        $label = ''
    ) {
        $this->type = $type;
        $this->screen = $screen;
        $this->label = $label;
    }

    public function type(): string
    {
        return 'validate';
    }

    /** @return array<string> */
    public function content(): array
    {
        return [
            'type'              => $this->type,
            'content'           => $this->content ?? '',
            'is_case_sensitive' => $this->caseSensitive,
            'is_whole_word'     => $this->wholeWord,
        ];
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function setCaseSensitive(bool $caseSensitive = true): self
    {
        $this->caseSensitive = $caseSensitive;

        return $this;
    }

    public function setWholeWord(bool $wholeWord = true): self
    {
        $this->wholeWord = $wholeWord;

        return $this;
    }

    public function toScreen(): array{
        return new Screen($this->screen);
    }

    public function withLabel(): array{
        return new Label($this->label);
    }
}
