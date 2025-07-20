<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

readonly class LocaleListener implements EventSubscriberInterface
{

    public function __construct(private string $defaultLocale = 'en')
    {
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        // NOTE TO FUTURE SELF:
        // hasPreviousSession prevents anonymous users from starting a session

        // overwrite _locale with request _locale first, otherwise use session variable
        if ($locale = $request->attributes->get('_locale')) {
            $request->getSession()->set('_locale', $locale);
        } else {
            $request->setLocale($request->getSession()->get('_locale', $this->defaultLocale));
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]], // higher prio than kernel localelistener
        ];
    }
}