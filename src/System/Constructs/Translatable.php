<?php


namespace App\System\Constructs;


abstract class Translatable
{
    protected string $type = 'value';

    public function __construct(private readonly string $message = '', private readonly array $arguments = [], private ?string $subject = null)
    {
        if ($this->subject) {
            $this->subject = strtolower($subject);
        }
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        if ($this->subject) {
            return $this->type . '.' . $this->subject . '.' . $this->message;
        }

        return $this->type . '.' . $this->message;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}