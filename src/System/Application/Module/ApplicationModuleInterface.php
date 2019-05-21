<?php

namespace App\System\Application\Module;

interface ApplicationModuleInterface
{
    public function getName(): string;

    public function setData(array $data): void;

    public function getData(): array;

    public function prepare();
}