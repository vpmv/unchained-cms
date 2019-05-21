<?php

namespace App\System\Application\Module;

class RedirectModule extends AbstractModule
{
    public function prepare()
    {
        $this->output = $this->data;
        return;
    }

    public function getName(): string
    {
        return 'redirect';
    }
}