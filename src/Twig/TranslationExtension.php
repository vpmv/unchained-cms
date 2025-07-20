<?php

namespace App\Twig;

use Symfony\Bridge\Twig\Extension\TranslationExtension as SymfonyTranslation;
use Symfony\Bridge\Twig\NodeVisitor\TranslationNodeVisitor;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Overrides final class with fallback
 */
class TranslationExtension extends AbstractExtension
{
    /** @var \Symfony\Bridge\Twig\Extension\TranslationExtension */
    private SymfonyTranslation $parent;

    public function __construct(
        private ?TranslatorInterface $translator = null,
        private ?TranslationNodeVisitor $translationNodeVisitor = null,
    ) {
        $this->parent = new SymfonyTranslation($this->translator, $this->translationNodeVisitor);
    }

    public function trans($message, array $arguments = [], $domain = null, $locale = null, $count = null)
    {
        $output = $this->parent->trans($message, $arguments, $domain, $locale);
        $defaultOutput = str_replace(array_keys($arguments), array_values($arguments), $message);
        if ($domain && $domain != 'messages' && ($output == $message || $output == $defaultOutput)) {
            $output = $this->parent->trans($message, $arguments, 'messages', $locale);
        }
        return $output;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('trans', $this->trans(...)),
        ];
    }
}
