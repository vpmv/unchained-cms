<?php

namespace App\System\Application\Database;

class Junction implements ValueInterface
{
    /** @var string */
    private $application;
    /** @var int */
    private $primaryKey;
    /** @var Column] */
    private $exposed = [];
    /** @var Column] */
    private $slug = [];
    /** @var Column */
    private $value = [];

    public function __construct(string $application, int $primaryKey, Column $value, Column $exposed, Column $slug)
    {
        $this->application = $application;
        $this->primaryKey  = $primaryKey;
        $this->value       = $value;
        $this->exposed     = $exposed;
        $this->slug        = $slug;
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