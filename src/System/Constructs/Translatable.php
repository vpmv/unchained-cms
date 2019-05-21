<?php


namespace App\System\Constructs;


abstract class Translatable
{
    protected $type = 'value';

    /** @var string */
    private $message = '';
    /** @var array */
    private $arguments = [];
    /** @var string|null */
    private $subject = null;

    public function __construct(string $message, array $arguments = [], ?string $subject = null)
    {
        $this->message   = $message;
        $this->subject   = strtolower($subject);
        $this->arguments = $arguments;
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