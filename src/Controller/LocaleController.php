<?php

namespace App\Controller;

use App\System\Configuration\ConfigStore;
use App\System\Configuration\Route as UnchainedRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use App\System\Configuration\RouteType;

class LocaleController extends AbstractController
{
    #[Route("/_locale/{_locale}", name: "locale_switch", requirements: ["_locale" => "[a-z]{2}"])]
    public function changeLocale(Request $request, RouterInterface $symfonyRouter, ConfigStore $configStore, $_locale): Response
    {
        $redirect = function (string $route = 'dash_main', array $params = [], int $status = 302) use ($request, $_locale): Response {
            $request->getSession()->set('_locale', $_locale);

            $url = $this->generateUrl($route, $params);
            return $this->redirect($url, $status);
        };
        $matchUri = function (string $uri) use ($configStore): ?UnchainedRoute {
            try {
                return $configStore->router->match($uri);
            } catch (NotFoundHttpException) {
                return null;
            }
        };

        $configStore->configureApplications();

        $referer = $request->headers->get('referer');
        if (!$referer) {
            return $redirect();
        }

        $uri = parse_url($referer, PHP_URL_PATH);
        try {
            $prevRequest = $symfonyRouter->match($uri);
        } catch (ResourceNotFoundException) {
            return $redirect();
        }

        $slug  = null;
        $route = null;
        switch ($prevRequest['_route']) {
            case RouteType::Category->value:
                $route = $matchUri($uri);
                break;
            case RouteType::Application->value:
                $route = $matchUri($uri);
                // uncategorized app with slug
                if (!$route) {
                    $category = $prevRequest['category'];
                    $slug     = $prevRequest['application'];
                    $route    = $matchUri("/$category/$slug");
                }
                break;
            case RouteType::ApplicationDetail->value:
                $category    = $prevRequest['category'];
                $application = $prevRequest['application'];
                $slug        = $prevRequest['slug'];
                $route       = $matchUri("/$category/$application");
                break;
        }

        if (null === $route) {
            return $redirect();
        }

        $newRoute = $configStore->router->matchApp($route->getCategoryId(), $route->getAppId(), $_locale);
        if ($slug) {
            $newRoute->addParameters(['slug' => $slug]);
        }

        return $redirect($newRoute->getName(), $newRoute->getParams());
    }

}