<?php

namespace App\System\Application\Database;

class Junction implements ValueInterface
{
    public function __construct(
        private string $application,
        private  int $primaryKey,
        private  Column $value,
        private  Column $exposed,
        private  Column $slug,
    ) {
    }

    /**
     * @return string
     */
    public function getApplication(): string
    {
        return $this->application;
    }

    public function getValue()
    {
        return $this->value->getValue();
    }

    public function getValueColumn()
    {
        return $this->value;
    }

    public function getExposed()
    {
        return $this->exposed->getValue();
    }


    public function getSlug()
    {
        return $this->slug->getValue();
    }


    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }
}