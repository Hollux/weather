<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/** Points d'entrée du flux OAuth Google : démarrage de la redirection et route de callback. */
class LoginController extends AbstractController
{
    /**
     * Redirige l'utilisateur vers Google (scopes email + profile). L'éventuel
     * `?redirect` est mémorisé dans un cookie court (10 min) car il ne survit pas
     * à l'aller-retour ; il est relu puis effacé dans GoogleAuthenticator.
     *
     * @Route("/connect/google", name="connect_google_start")
     */
    public function connect(ClientRegistry $clientRegistry, Request $request): Response
    {
        $response = $clientRegistry->getClient('google')->redirect(['email', 'profile'], []);

        $redirect = $request->query->get('redirect');
        if (is_string($redirect) && $redirect !== '') {
            $response->headers->setCookie(Cookie::create(
                'post_login_redirect',
                $redirect,
                new \DateTimeImmutable('+10 minutes'),
                '/',
                null,
                true,
                true,
                false,
                Cookie::SAMESITE_LAX,
            ));
        }

        return $response;
    }

    /**
     * Callback OAuth : entièrement traité par GoogleAuthenticator, ce corps reste vide.
     *
     * @Route("/connect/google/check", name="connect_google_check")
     */
    public function check(): void
    {
        // Géré par l'authenticator
    }
}
