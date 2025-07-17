<?php

namespace App\System\Application\Database;

class Column implements ValueInterface
{
    public function __construct(private string $name, private mixed $value = null)
    {
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