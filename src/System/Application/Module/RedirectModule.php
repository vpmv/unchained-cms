<?php

namespace App\System\Application\Module;

class RedirectModule extends AbstractModule
{
    public function prepare(): void
    {
        $this->output = $this->data;
    }

    public function getName(): string
    {
        return 'redirect';
    }
}