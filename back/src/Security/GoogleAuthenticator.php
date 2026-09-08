<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class GoogleAuthenticator extends OAuth2Authenticator implements AuthenticationEntryPointInterface
{
    private const DEFAULT_FRONTEND_URL = 'https://weather.hollux.fr';

    public function __construct(
        private ClientRegistry $clientRegistry,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    /** N'entre en jeu que sur la route de callback OAuth Google (`connect_google_check`). */
    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    /**
     * Échange le code OAuth contre un utilisateur : le retrouve par googleId,
     * sinon par email, sinon le crée. Sur un compte existant, complète googleId
     * et email manquants sans jamais écraser une valeur déjà présente.
     */
    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);

                $googleId = $googleUser->getId();
                $email = $googleUser->getEmail();

                $user = $this->userRepository->findOneBy(['googleId' => $googleId]);

                if (!$user && $email) {
                    $user = $this->userRepository->findOneBy(['email' => $email]);
                }

                if (!$user) {
                    $user = new User();
                    $user->setUsername($this->generateUsername($email, $googleId));
                    $user->setEmail($email);
                    $user->setGoogleId($googleId);
                    $user->setRoles(['ROLE_USER']);
                    $user->setPassword(null);
                } else {
                    if (!$user->getGoogleId()) {
                        $user->setGoogleId($googleId);
                    }

                    if (!$user->getEmail() && $email) {
                        $user->setEmail($email);
                    }
                }

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return $user;
            })
        );
    }

    /**
     * Émet un JWT pour l'utilisateur authentifié et redirige vers le front, en
     * lui passant le token en query string. La cible est celle déposée par
     * LoginController::connect() (cookie) ou un `?redirect`, validée par
     * isAllowedRedirect() ; repli sur DEFAULT_FRONTEND_URL sinon.
     */
    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return new JsonResponse(['error' => 'Invalid user'], Response::HTTP_UNAUTHORIZED);
        }

        $jwt = $this->jwtManager->create($user);

        $target = $request->cookies->get('post_login_redirect')
            ?? $request->query->get('redirect');
        $frontendUrl = (is_string($target) && self::isAllowedRedirect($target))
            ? $target
            : self::DEFAULT_FRONTEND_URL;

        $separator = str_contains($frontendUrl, '?') ? '&' : '?';
        $response = new RedirectResponse($frontendUrl . $separator . 'token=' . urlencode($jwt));
        $response->headers->clearCookie('post_login_redirect', '/');

        return $response;
    }

    /** Répond 401 JSON quand l'authentification Google échoue. */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => 'Google login failed',
            'message' => $exception->getMessageKey(),
        ], Response::HTTP_UNAUTHORIZED);
    }

    /** Point d'entrée du firewall : répond 401 JSON pour une ressource protégée demandée sans authentification. */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse([
            'error' => 'Authentication required',
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * N'autorise une redirection post-login que vers nos propres domaines
     * (hollux.fr et ses sous-domaines, plus localhost en dev). Empêche qu'un
     * ?redirect forgé n'exfiltre le JWT vers un site tiers.
     */
    private static function isAllowedRedirect(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host']);

        return $host === 'hollux.fr'
            || str_ends_with($host, '.hollux.fr')
            || $host === 'localhost'
            || $host === '127.0.0.1';
    }

    /**
     * Fabrique un username à partir de la partie locale de l'email suffixée
     * d'un fragment de l'id Google (ou `google_<id>` en l'absence d'email).
     */
    private function generateUsername(?string $email, string $googleId): string
    {
        if ($email) {
            $base = preg_replace('/[^a-zA-Z0-9_.-]/', '', explode('@', $email)[0]);

            return $base . '_' . substr($googleId, 0, 6);
        }

        return 'google_' . substr($googleId, 0, 12);
    }
}
