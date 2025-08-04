<?php

namespace App\System\Application\Database;

readonly class Junction implements ValueInterface
{
    public function __construct(
        private string $application,
        private int $primaryKey,
        private Column $value,
        private Column $exposed,
        private Column $slug,
    ) {
    }

    /**
     * @return string
     */
    public function getApplication(): string
    {
        return $this->application;
    }

    public function getValue(): mixed
    {
        return $this->value->getValue();
    }

    public function getValueColumn(): Column
    {
        return $this->value;
    }

    public function getExposed(): mixed
    {
        return $this->exposed->getValue();
    }


    public function getSlug(): mixed
    {
        return $this->slug->getValue();
    }


    public function getPrimaryKey(): int
    {
        return $this->primaryKey;
    }
}