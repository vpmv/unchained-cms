<?php


namespace App\System\Application\Module;


use App\System\Application\Application;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractModule implements ApplicationModuleInterface
{
    protected array $data   = [];
    protected array $output = [];
    protected array $errors = [];

    public function __construct(
        protected Application $container,
        protected Request $request,
    ) {
    }

    public function getData(): array
    {
        return $this->output;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    abstract public function prepare(): void;

    abstract public function getName(): string;
}