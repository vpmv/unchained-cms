<?php

namespace App\Controller;

use App\System\Application\Category;
use App\System\ApplicationManager;
use App\System\Configuration\ConfigStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Main application parser
 *
 * @package App\Controller
 *
 */
#[Route(name: "dash_")]
class DashboardController extends AbstractController
{
    /**
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    #[Route("/refresh", name: "refresh")]
    public function clearCache(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED');
        $cache = new FilesystemAdapter();
        $cache->clear();

        $referer = $request->headers->get('referer', '/');

        return $this->redirect($referer);
    }

    #[Route("/", name: "main")]
    public function dashboard(ApplicationManager $factory, ConfigStore $configStore): Response
    {
        $applications = $factory->getApplications(true);

        $unchainedConfig = $configStore->getUnchainedConfig();
        if ($unchainedConfig->getDashboard('show_counter', true)) {
            $factory->addRecordCount($applications);
        }

        return $this->render('main/dashboard.html.twig', ['applications' => $applications]);
    }

    /**
     * Category index
     *
     * May be captured by impostor Application: i.e. uncategorized
     *
     * @param \App\System\ApplicationManager                $factory
     * @param \App\System\Configuration\ConfigStore         $configStore
     * @param \Symfony\Component\Translation\LocaleSwitcher $localeSwitcher
     * @param                                               $application
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route("/{application}", name: "category", requirements: ['application' => "[0-9a-z\-]{3,}"])]
    public function category(ApplicationManager $factory, ConfigStore $configStore, $application): Response
    {
        $route = $configStore->router->match("/$application");
        if ($res = $configStore->router->resolve($route)) {
            return $res;
        }

        $data = $factory->runApplication($route);
        if (!empty($data['redirect'])) {
            return $this->redirect($this->generateUrl($data['redirect']->getName(), $data['redirect']->getParams()));
        }

        $applications = $factory->getApplications(true);
        if ($factory->activeApp instanceof Category) {
            if ($configStore->getUnchainedConfig()->getDashboard('show_counter', true)) {
                $factory->addRecordCount($applications, $factory->activeApp->appId);
            }

            return $this->render('applications/category_index.html.twig', [
                'applications' => $applications,
                'category'     => $data,
            ]);
        }

        return $this->render('applications/' . $factory->activeApp->getCurrentModule()->getName() . '.html.twig', [
            'category'     => $data['category'],
            'application'  => $data,
            'applications' => $applications,
        ]);
    }

    #[Route("/{category}/{application}", name: "application", requirements: ['category' => "[0-9a-z\-]{3,}", 'application' => "[0-9a-z\-]{3,}"])]
    public function application(ApplicationManager $factory, ConfigStore $configStore, $category, $application): Response
    {
        try {
            $route = $configStore->router->match("/$category/$application");
        } catch (NotFoundHttpException) {
            $route = $configStore->router->match("/$category"); // <application> might be a slug (ELSE hard fail)
            $route->addParameters(['slug' => $application]);
        }
        if ($res = $configStore->router->resolve($route)) {
            return $res;
        }

        if ($category == '_default') {
            throw new \LogicException('not implemented');
        }

        $data = $factory->runApplication($route);

        if (!empty($data['redirect'])) {
            return $this->redirect($this->generateUrl($data['redirect']->getName(), $data['redirect']->getParams()));
        }

        $applications = $factory->getApplications(true); // required for menu
        return $this->render('applications/' . $factory->activeApp->getCurrentModule()->getName() . '.html.twig', [
            'category'     => $data['category'],
            'application'  => $data,
            'applications' => $applications,
        ]);
    }

    #[Route("/{category}/{application}/{slug}", name: "application_detail", requirements: ['category' => "[0-9a-z\-]{3,}", 'application' => "[0-9a-z\-]{3,}"])]
    public function applicationDetail(ApplicationManager $factory, ConfigStore $configStore, $category, $application, $slug): Response
    {
        $route = $configStore->router->match("/$category/$application");
        $route->addParameters(['slug' => $slug]);
        if ($res = $configStore->router->resolve($route)) {
            return $res;
        }

        $data = $factory->runApplication($route);

        if (!empty($data['redirect'])) {
            return $this->redirect($this->generateUrl($data['redirect']->getName(), $data['redirect']->getParams()));
        }

        $applications = $factory->getApplications(true); // required for menu
        return $this->render('applications/detail.html.twig', [
            'category'     => $data['category'],
            'application'  => $data,
            'applications' => $applications,
        ]);
    }

}