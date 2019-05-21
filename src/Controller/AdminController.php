<?php


namespace App\Controller;

use App\System\ApplicationManager;
use App\System\RepositoryManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * @Route(name="admin_")
 */
class AdminController extends AbstractController
{
    /**
     * @Route(path="/admin/{category}/{app}/{uuid}", requirements={"app"="[a-z\-]+", "uuid"="[\w]+"}, defaults={"uuid"=null}, name="edit")
     */
    public function edit($category, $app, $uuid, ApplicationManager $applicationManager)
    {
        try {
            $application = $applicationManager->getApplication($app, $category);
        } catch (\InvalidArgumentException $e) {
            throw new NotFoundHttpException('App not found');
        }

        $application->boot('form');
        $application->apply(['id' => $uuid]); // fixme: construct primary identifier
        $data = $application->run();

        if (!empty($data['redirect'])) {
            $redirect = $data['redirect'];
            return $this->redirect($this->generateUrl($redirect['route'], $redirect['params']));
        }

        $applications = $applicationManager->getApplications(true);

        return $this->render('applications/form.html.twig', ['applications' => $applications, 'application' => $data]);
    }

    /**
     * @Route("/login", name="login")
     */
    public function login(AuthenticationUtils $utils)
    {
        $error = $utils->getLastAuthenticationError();
        $lastUser = $utils->getLastUsername();

        $form = $this->createFormBuilder(null, ['attr' => ['novalidate' => true]])
            ->setAction($this->generateUrl('admin_login'))
            ->add('_username', TextType::class, ['label' => 'Username'])
            ->add('_password', PasswordType::class, ['label' => 'Password'])
            ->add('Login', SubmitType::class)
            ->getForm();
        $form->setData(['_username' => $lastUser]);

        return $this->render('main/login.html.twig', [
            'error' => $error,
            'form' => $form->createView(),
        ]);
    }
}