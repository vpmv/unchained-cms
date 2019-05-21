<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class LocaleController extends AbstractController
{
    /**
     * @Route("/_locale/{_locale}", name="locale_switch", requirements={"_locale"="[a-z]{2}"})
     */
    public function changeLocale(Request $request)
    {
        $redirect = $this->generateUrl('dash_main');

        /*
         * FIXME: categorization broke direct redirects; a possible fix is caching the active app/cat
        $referer = $request->headers->get('referer');
        $host = $request->getHost();
        $pathInfo = substr($referer, strpos($referer, $host) + strlen($host));
        try {
            $prevRequest = $this->get('router')->match($pathInfo);
            $duplicateRequest = $prevRequest['_route'] == 'locale_switch';
        } catch (ResourceNotFoundException $e) {
            $duplicateRequest = true;
        }

        $redirect = $referer;
        if (strpos($referer, $host) === false || $duplicateRequest) {
            $redirect = $this->generateUrl('dash_main');
        }*/

        return $this->redirect($redirect);
    }
}