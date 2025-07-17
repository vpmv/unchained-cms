<?php

namespace App\System\Application\Database;

class JunctionList implements ValueInterface
{
    /** @var Junction[] */
    private $junctions = [];

    public function __construct(private string $application, Junction ...$junctions)
    {
        foreach ($junctions as $junction) {
            $this->junctions[$junction->getPrimaryKey()] = $junction;
        }
    }

    /**
     * @return string
     */
    public function getApplication(): string
    {
        return $this->application;
    }

    /**
     * @return \App\System\Application\Database\Junction[]
     */
    public function getJunctions(): array
    {
        return $this->junctions;
    }
}