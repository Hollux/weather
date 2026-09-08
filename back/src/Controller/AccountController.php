<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

/** Gestion par l'utilisateur connecté de son propre compte (profil, mot de passe) sous /me (appelé par le front en /api/me). */
class AccountController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    /**
     * Renvoie le profil de l'utilisateur connecté.
     *
     * @Route("/me", name="account_show", methods={"GET"})
     */
    public function show(): JsonResponse
    {
        return new JsonResponse($this->userToArray($this->currentUser()));
    }

    /**
     * Met à jour username et/ou email de l'utilisateur connecté (non vides, email valide, unicité hors soi-même).
     *
     * @Route("/me", name="account_update", methods={"PATCH"})
     */
    public function update(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $data = json_decode($request->getContent(), true) ?? [];

        if (array_key_exists('username', $data)) {
            $username = trim((string) $data['username']);

            if ($username === '') {
                return new JsonResponse(['error' => "Le nom d'utilisateur ne peut pas être vide"], Response::HTTP_BAD_REQUEST);
            }

            $existing = $this->userRepository->findOneBy(['username' => $username]);
            if ($existing && $existing->getId() !== $user->getId()) {
                return new JsonResponse(['error' => "Ce nom d'utilisateur est déjà pris"], Response::HTTP_CONFLICT);
            }

            $user->setUsername($username);
        }

        if (array_key_exists('email', $data)) {
            $email = trim((string) $data['email']);

            if ($email === '') {
                return new JsonResponse(['error' => "L'email ne peut pas être vide"], Response::HTTP_BAD_REQUEST);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return new JsonResponse(['error' => 'Adresse email invalide'], Response::HTTP_BAD_REQUEST);
            }

            $existing = $this->userRepository->findOneBy(['email' => $email]);
            if ($existing && $existing->getId() !== $user->getId()) {
                return new JsonResponse(['error' => 'Cet email est déjà utilisé'], Response::HTTP_CONFLICT);
            }

            $user->setEmail($email);
        }

        $this->entityManager->flush();

        return new JsonResponse($this->userToArray($user));
    }

    /**
     * Change le mot de passe (≥ 8 caractères). Exige le mot de passe actuel
     * seulement si le compte en a déjà un (un compte Google pur peut en définir
     * un sans preuve préalable).
     *
     * @Route("/me/password", name="account_password_update", methods={"POST"})
     */
    public function updatePassword(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $user = $this->currentUser();
        $data = json_decode($request->getContent(), true) ?? [];

        $newPassword = (string) ($data['newPassword'] ?? '');

        if (strlen($newPassword) < 8) {
            return new JsonResponse(['error' => 'Le mot de passe doit contenir au moins 8 caractères'], Response::HTTP_BAD_REQUEST);
        }

        if ($user->getPassword() !== null) {
            $currentPassword = (string) ($data['currentPassword'] ?? '');

            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                return new JsonResponse(['error' => 'Mot de passe actuel incorrect'], Response::HTTP_BAD_REQUEST);
            }
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
        $this->entityManager->flush();

        return new JsonResponse($this->userToArray($user));
    }

    /** L'utilisateur connecté, ou 403 s'il n'y en a pas. */
    private function currentUser(): User
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    /** Sérialise un User pour l'API (sans mot de passe : `hasPassword` seulement). */
    private function userToArray(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'hasPassword' => $user->getPassword() !== null,
            'hasGoogle' => $user->getGoogleId() !== null,
            'createdAt' => $user->getCreatedAt()?->format(DATE_ATOM),
        ];
    }
}
