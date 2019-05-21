<?php

namespace App\System\Application\Translation;

use App\System\Constructs\Translatable;

class TranslatableUserOutput extends Translatable
{
    protected $type = 'user';

    public function __construct(string $subject, string $message, array $arguments = [])
    {
        $subject = preg_replace('/[^a-zA-Z_-]/', '_', $subject);
        parent::__construct($message, $arguments, $subject);
    }
}