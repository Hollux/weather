<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Bootstrap du premier compte admin (aucune interface ne permet de le faire
 * soi-même tant qu'aucun admin n'existe déjà).
 *
 *   php bin/console app:user:promote-admin <username-ou-email>
 */
class PromoteUserAdminCommand extends Command
{
    protected static $defaultName = 'app:user:promote-admin';

    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(UserRepository $userRepository, EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
    }

    protected function configure()
    {
        $this
            ->setDescription('Donne le rôle ROLE_ADMIN à un compte existant (par username ou email).')
            ->addArgument('identifier', InputArgument::REQUIRED, 'Username ou email du compte à promouvoir');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $identifier = $input->getArgument('identifier');

        $user = $this->userRepository->findOneByUsernameOrEmail($identifier);

        if (!$user) {
            $io->error(sprintf('Aucun compte trouvé pour "%s".', $identifier));

            return Command::FAILURE;
        }

        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true)) {
            $io->note(sprintf('"%s" est déjà admin.', $user->getUsername()));

            return Command::SUCCESS;
        }

        $roles[] = 'ROLE_ADMIN';
        $user->setRoles(array_values(array_unique($roles)));
        $this->entityManager->flush();

        $io->success(sprintf('"%s" est maintenant admin.', $user->getUsername()));

        return Command::SUCCESS;
    }
}
