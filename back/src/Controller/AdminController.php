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
use Symfony\Component\Routing\Annotation\Route;

/**
 * Administration des comptes utilisateur sous /admin (appelé par le front en
 * /api/admin, réservé à ROLE_ADMIN via security.yaml) : liste et gestion des rôles.
 */
class AdminController extends AbstractController
{
    private const ASSIGNABLE_ROLES = ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_CRANGE'];

    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {
    }

    /**
     * Liste tous les comptes pour la page d'admin.
     *
     * @Route("/admin/users", name="admin_users_list", methods={"GET"})
     */
    public function listUsers(): JsonResponse
    {
        $users = array_map(
            fn (User $user) => $this->userToArray($user),
            $this->userRepository->findAll()
        );

        return new JsonResponse($users);
    }

    /**
     * Remplace les rôles d'un compte (parmi ASSIGNABLE_ROLES) ; interdit à un admin de se retirer ROLE_ADMIN.
     *
     * @Route("/admin/users/{id}/roles", name="admin_users_update_roles", methods={"PATCH"})
     */
    public function updateRoles(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $roles = $data['roles'] ?? null;

        if (!is_array($roles)) {
            return new JsonResponse(['error' => 'Le champ "roles" doit être un tableau'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($roles as $role) {
            if (!in_array($role, self::ASSIGNABLE_ROLES, true)) {
                return new JsonResponse(['error' => sprintf('Rôle inconnu : "%s"', $role)], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($this->isSelf($user) && !in_array('ROLE_ADMIN', $roles, true)) {
            return new JsonResponse(['error' => 'Impossible de te retirer le rôle admin toi-même'], Response::HTTP_BAD_REQUEST);
        }

        $user->setRoles(array_values(array_unique($roles)));
        $this->entityManager->flush();

        return new JsonResponse($this->userToArray($user));
    }

    /**
     * Supprime un compte ; interdit sur soi-même.
     *
     * @Route("/admin/users/{id}", name="admin_users_delete", methods={"DELETE"})
     */
    public function deleteUser(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }

        if ($this->isSelf($user)) {
            return new JsonResponse(['error' => 'Impossible de supprimer ton propre compte'], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    /** Vrai si `$user` est l'administrateur qui fait la requête (garde-fou anti auto-modification). */
    private function isSelf(User $user): bool
    {
        $currentUser = $this->security->getUser();

        return $currentUser instanceof User && $currentUser->getId() === $user->getId();
    }

    /** Sérialise un User pour la page d'admin (drapeaux Google/mot de passe). */
    private function userToArray(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'hasGoogle' => $user->getGoogleId() !== null,
            'hasPassword' => $user->getPassword() !== null,
            'createdAt' => $user->getCreatedAt()?->format(DATE_ATOM),
        ];
    }
}
