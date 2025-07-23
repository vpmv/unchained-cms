<?php

namespace App\Controller;

use App\System\Application\Category;
use App\System\ApplicationManager;
use App\System\Configuration\ConfigStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    #[Route("/refresh", name: "refresh")]
    public function clearCache(): RedirectResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED');
        $cache = new FilesystemAdapter();
        $cache->clear();

        return $this->redirect('/');
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

    #[Route("/{app}", name: "app", requirements: ['app' => "[0-9a-z\-\/]{3,}"])]
    public function application($app, ApplicationManager $factory, ConfigStore $configStore): Response
    {
        $application = $factory->getApplicationByPath($app);
        $data        = $application->run();

        if ($data === null) {
            throw new BadRequestHttpException('Not accepted');
        }

        if (!empty($data['redirect'])) {
            return $this->redirect($this->generateUrl('dash_app', ['app' => $data['redirect']]));
        }

        $applications = $factory->getApplications(true);
        if ($application instanceof Category) {
            $unchainedConfig = $configStore->getUnchainedConfig();
            if ($unchainedConfig->getDashboard('show_counter', true)) {
                $factory->addRecordCount($applications, $application->appId);
            }

            return $this->render('applications/category_index.html.twig', [
                'applications' => $applications,
                'category'     => $data,
            ]);
        }

        return $this->render('applications/' . $application->getCurrentModule()->getName() . '.html.twig', [
            'category'     => $data['category'],
            'application'  => $data,
            'applications' => $applications,
        ]);
    }
}