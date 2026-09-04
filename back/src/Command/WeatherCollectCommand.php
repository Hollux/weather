<?php

namespace App\Command;

use App\Service\WeatherTools;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Collecte la mesure météo courante depuis OpenWeatherMap.
 *
 * Job interne, lancé toutes les 5 min par le planificateur (service "cron" du
 * docker-compose). Chemin sûr de remplacement de la route HTTP /saveminutly :
 * plus aucun endpoint exposé, plus aucun secret à faire transiter.
 *
 * Pendant la bascule, la route HTTP est conservée en secours et l'ancien cron
 * hôte est désactivé (commenté), pas supprimé.
 *
 *   php bin/console app:weather:collect
 */
class WeatherCollectCommand extends Command
{
    protected static $defaultName = 'app:weather:collect';

    private WeatherTools $weatherTools;

    public function __construct(WeatherTools $weatherTools)
    {
        parent::__construct();
        $this->weatherTools = $weatherTools;
    }

    protected function configure(): void
    {
        $this->setDescription('Récupère la mesure OpenWeatherMap courante et l\'enregistre (cron 5 min).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stamp = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        try {
            $saved = $this->weatherTools->setMinutelyHW();
        } catch (\Throwable $e) {
            $output->writeln(sprintf('[%s] app:weather:collect ERREUR : %s', $stamp, $e->getMessage()));

            return Command::FAILURE;
        }

        if (!$saved) {
            $output->writeln(sprintf('[%s] app:weather:collect : réponse API vide, rien enregistré.', $stamp));

            return Command::FAILURE;
        }

        $output->writeln(sprintf('[%s] app:weather:collect : mesure enregistrée.', $stamp));

        return Command::SUCCESS;
    }
}
