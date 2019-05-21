<?php

namespace App\System\Application\Database;

class Column implements ValueInterface
{
    /** @var string */
    private $name;
    /** @var mixed|null */
    private $value = null;

    public function __construct(string $name, $value = null)
    {
        $this->name  = $name;
        $this->setValue($value);
    }

    private function setValue($value): void
    {
        $this->value = $value;
        if (is_array($v = json_decode($value, true))) {
            $this->value = $v;
        } elseif (is_array($v = @unserialize($value))) {
            $this->value = $v;
        }
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return mixed|null
     */
    public function getValue()
    {
        return $this->value;
    }
}