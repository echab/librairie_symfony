<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render(
            'login/index.html.twig',
            [
                // 'controller_name' => 'LoginController',
                'last_username' => $lastUsername,
                'error' => $error,
            ],
        );
    }

    #[Route("/wp-login{ext<\.php>}", name: 'wp_login', methods: ['GET', 'POST'], defaults: ['ext' => '.php'])]
    public function loginWp(
        Request $request,
        UserProviderInterface $userProvider,
        UserPasswordHasherInterface $passwordHasher,
        // \Psr\Log\LoggerInterface $logger,
    ): Response
    {
        $payload = $request->getPayload();
        $log = $payload->getString('log');
        $pswd = $payload->getString('pwd');
        $redirectTo = $payload->getString('redirect_to', $request->getSchemeAndHttpHost() . '/');
        $getNonce = $payload->getString('getNonce');

        // $logger->error("loginWp: request->header=$request->headers"); // for DEBUG
        // $logger->error("loginWp: log=$log getNonce=$getNonce redirect_to=$redirectTo"); // for DEBUG

        if (!($userProvider instanceof InMemoryUserProvider)) {
            throw $this->createAccessDeniedException('Unsupported user provider');
            // also for $user type inference
        }

        $user = $userProvider->loadUserByIdentifier($log);
        if (
            !$user->isEnabled()
            || !in_array('ROLE_STOCK', $user->getRoles())
            || !$passwordHasher->isPasswordValid($user, $pswd)
        ) {
            throw $this->createAccessDeniedException('Invalid user or password');
        }

        $response = $this->redirect($redirectTo);
        if ($getNonce === '1') {
            // store nonce in the session and in a cookie
            $nonce = bin2hex(random_bytes(16));
            $request->getSession()->set('nonce', $nonce);

            $response->headers->setCookie(new Cookie(
                'x-head',
                $nonce,
                0, // date_create()->add(new \DateInterval('PT60S')),
                '/',
                $request->getHost(),
                $request->isSecure()
            ));

            // $logger->error("loginWp: headers=$response->headers"); // for DEBUG
        }
        return $response;
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(?string $redirect): Response
    {
        return $this->redirect($redirect ?? $this->generateUrl('home'));
    }

    #[Route("/logout_basic", name: "app_logout_basic")]
    public function logout_http_basic(): Response
    {
        return new Response(
            $this->renderView(
                'login/logout-basic.html.twig',
                ['redirect' => '/'],
            ),
            Response::HTTP_UNAUTHORIZED,
            [
                'WWW-Authenticate' => 'Basic realm="Admin Area"',
            ]
        );
    }
}
