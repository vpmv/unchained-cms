<?php

namespace App\System\Application\Translation;

use App\System\Application\Field;
use App\System\Constructs\Translatable;

class TranslatableChoice extends Translatable
{
    protected $type = 'choice';

    public function __construct(Field $field, string $message, array $arguments = [])
    {
        parent::__construct($message, $arguments, $field->getId());
    }
}