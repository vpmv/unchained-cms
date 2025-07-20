<?php


namespace App\Controller;

use App\System\ApplicationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Routing\Attribute\Route;

#[Route(name: "admin_")]
class AdminController extends AbstractController
{
    #[Route("/admin/{category}/{app}/{uuid}", requirements: ["app" => "[a-z\-]+", "uuid" => "[\w]+"], defaults: ["uuid" => null], name: "edit")]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(ApplicationManager $applicationManager, $category, $app, $uuid): Response
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

    #[Route("/login", name: "login")]
    public function login(AuthenticationUtils $utils, ApplicationManager $factory): Response
    {
        $error    = $utils->getLastAuthenticationError();
        $lastUser = $utils->getLastUsername();

        $form = $this->createFormBuilder(null, ['attr' => ['novalidate' => true]])
            ->setAction($this->generateUrl('admin_login'))
            ->add('_username', TextType::class, ['label' => 'Username'])
            ->add('_password', PasswordType::class, ['label' => 'Password'])
            ->add('Login', SubmitType::class)
            ->getForm();
        $form->setData(['_username' => $lastUser]);

        return $this->render('main/login.html.twig', [
            'error'        => $error,
            'form'         => $form->createView(),
            'applications' => $factory->getApplications(true),
        ]);
    }
}