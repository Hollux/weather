<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\Service\WeatherTools;

class TestCommand extends Command {

    private $weatherTools;
    public function __construct(WeatherTools $weatherTools)
    {
        $this->weatherTools = $weatherTools;
        parent::__construct();
    }

    protected function configure () {
        // On set le nom de la commande
        $this->setName('crontest');

        // On set la description
        $this->setDescription("Permet juste de faire un test dans la console");

        // On set l'aide
        $this->setHelp("Je serai affiche si on lance la commande app/console app:test -h");

        // On prépare les arguments
        $this->addArgument('name', InputArgument::OPTIONAL, "Quel est ton prenom ?")
                ->addArgument('last_name', InputArgument::OPTIONAL, "Quel est ton nom ?");
    }

    public function execute(InputInterface $input, OutputInterface $output): int 
    {
        $this->weatherTools->setMinutelyHW();

        return Command::SUCCESS;
    }
}