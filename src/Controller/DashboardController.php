<?php

namespace App\Controller;

use App\System\Application\Category;
use App\System\ApplicationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
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
    #[Route("/refresh")]
    public function clearCache(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED');
        $cache = new FilesystemAdapter();
        $cache->clear();

        return $this->redirect('/');
    }

    #[Route("/", name: "main")]
    public function dashboard(ApplicationManager $factory)
    {
        $applications = $factory->getApplications(true);

        return $this->render('main/dashboard.html.twig', ['applications' => $applications]);
    }
    #[Route("/{app}", name: "app", requirements: ['app' => "[0-9a-z\-\/]{3,}"])]
    public function application($app, ApplicationManager $factory)
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
            return $this->render('applications/category_index.html.twig', ['applications' => $applications, 'category' => $data]);
        }

        return $this->render('applications/' . $application->getCurrentModule()->getName() . '.html.twig', ['applications' => $applications, 'application' => $data]);
    }
}