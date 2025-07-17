<?php

namespace App\System\Application\Database;

class ApplicationReference implements ValueInterface
{

    public function __construct(private string $applicationAlias, private mixed $value = null)
    {
    }

    /**
     * @return string
     */
    public function getApplicationAlias(): string
    {
        return $this->applicationAlias;
    }

    /**
     * @return mixed|null
     */
    public function getValue()
    {
        return $this->value;
    }
}