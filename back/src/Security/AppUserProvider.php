<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class AppUserProvider implements UserProviderInterface
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    /** Charge un utilisateur par username ou email (identifiant de connexion) ; lève UserNotFoundException sinon. */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userRepository->findOneByUsernameOrEmail($identifier);

        if (!$user) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        return $user;
    }

    /** @deprecated depuis Symfony 5.3, encore requis par UserProviderInterface en 5.4 ; délègue à loadUserByIdentifier(). */
    public function loadUserByUsername(string $username): UserInterface
    {
        return $this->loadUserByIdentifier($username);
    }

    /** Recharge l'utilisateur depuis la base à chaque requête (firewall stateless) ; rejette toute classe non-User. */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        $refreshed = $this->userRepository->find($user->getId());

        if (!$refreshed) {
            throw new UserNotFoundException('User not found while refreshing.');
        }

        return $refreshed;
    }

    /** Ce provider gère la classe User et ses sous-classes. */
    public function supportsClass(string $class): bool
    {
        return $class === User::class || is_subclass_of($class, User::class);
    }
}
