<?php

namespace App\System\Application\Database;

class ApplicationReference implements ValueInterface
{
    /** @var string */
    private $applicationAlias;
    /** @var mixed|null */
    private $value = null;

    public function __construct(string $sourceAlias, $value = null)
    {
        $this->applicationAlias = $sourceAlias;
        $this->value            = $value;
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