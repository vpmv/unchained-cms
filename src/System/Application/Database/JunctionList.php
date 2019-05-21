<?php

namespace App\System\Application\Database;

class JunctionList implements ValueInterface
{
    /** @var string */
    private $application;
    /** @var Junction[] */
    private $junctions = [];

    public function __construct(string $application, Junction ...$junctions)
    {
        $this->application = $application;
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