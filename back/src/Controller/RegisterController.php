<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

/** Inscription par email + mot de passe (POST /register, appelé par le front en /api/register). */
class RegisterController extends AbstractController
{
    /**
     * Crée un compte local après validation (champs requis, mot de passe ≥ 8
     * caractères, email valide, username et email encore libres) et renvoie un
     * JWT. Refuse un email déjà rattaché à un compte Google.
     *
     * @Route("/register", name="api_register", methods={"POST"})
     */
    public function register(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];

        $username = trim((string) ($data['username'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $email === '' || $password === '') {
            return new JsonResponse(['error' => "Nom d'utilisateur, email et mot de passe sont requis"], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($password) < 8) {
            return new JsonResponse(['error' => 'Le mot de passe doit contenir au moins 8 caractères'], Response::HTTP_BAD_REQUEST);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Adresse email invalide'], Response::HTTP_BAD_REQUEST);
        }

        if ($userRepository->findOneBy(['username' => $username])) {
            return new JsonResponse(['error' => "Ce nom d'utilisateur est déjà pris"], Response::HTTP_CONFLICT);
        }

        $existingByEmail = $userRepository->findOneBy(['email' => $email]);

        if ($existingByEmail) {
            if ($existingByEmail->getPassword() === null) {
                return new JsonResponse(['error' => 'Cet email est déjà associé à un compte Google. Connecte-toi avec Google.'], Response::HTTP_CONFLICT);
            }

            return new JsonResponse(['error' => 'Cet email est déjà utilisé'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse([
            'token' => $jwtManager->create($user),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
        ], Response::HTTP_CREATED);
    }
}
