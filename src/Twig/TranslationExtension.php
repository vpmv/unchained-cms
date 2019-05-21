<?php

namespace App\Twig;

class TranslationExtension extends \Symfony\Bridge\Twig\Extension\TranslationExtension
{
    public function trans($message, array $arguments = [], $domain = null, $locale = null, $count = null)
    {
        $output = parent::trans($message, $arguments, $domain, $locale);
        $defaultOutput = str_replace(array_keys($arguments), array_values($arguments), $message);
        if ($domain && $domain != 'messages' && ($output == $message || $output == $defaultOutput)) {
            $output = parent::trans($message, $arguments, 'messages', $locale);
        }
        return $output;
    }
}