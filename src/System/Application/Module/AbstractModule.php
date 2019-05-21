<?php


namespace App\System\Application\Module;


use App\System\Application\Application;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractModule implements ApplicationModuleInterface
{
    /** @var \App\System\Application\Application */
    protected $container;

    /** @var \Symfony\Component\HttpFoundation\Request */
    protected $request;

    protected $data   = [];
    protected $output = [];

    public function __construct(Application $container, Request $request)
    {
        $this->request   = $request;
        $this->container = $container;
    }

    public function getData(): array
    {
        return $this->output;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    abstract public function prepare();

    abstract public function getName(): string;
}