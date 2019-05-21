<?php

namespace App\System\Helpers;

use Symfony\Component\Stopwatch\Stopwatch;

class Timer
{
    /** @var \Symfony\Component\Stopwatch\Stopwatch */
    protected $stopwatch;

    private $category  = null;
    private $lastEvent = null;

    public function __construct(Stopwatch $stopwatch)
    {
        $this->stopwatch = $stopwatch;
    }

    public function setCategory(string $name)
    {
        $this->category = $name;
    }

    public function start(string $name, ?string $category = null)
    {
        $this->category = $this->category ?: ($category ?: debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[1]['class']); // FIXME

        $this->lastEvent = $name;
        $this->stopwatch->start($name, $this->category);
    }

    public function stop(?string $name = null)
    {
        if ($name || $this->lastEvent) {
            $this->stopwatch->stop($name ?: $this->lastEvent);
        }
    }

    public function timeout(?string $name = null)
    {
        if ($name || $this->lastEvent) {
            $this->stopwatch->lap($name ?: $this->lastEvent);
        }
    }

    public function reset()
    {
        $this->stopwatch->reset();
    }
}