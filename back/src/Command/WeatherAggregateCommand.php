<?php

namespace App\Command;

use App\Service\WeatherDaily;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Agrège les mesures weather_hwminutely en lignes journalières weather_daily.
 *
 * Job interne, lancé une fois par jour par le planificateur (service "cron" du
 * docker-compose). Chemin sûr de remplacement de la route HTTP /generateWeatherDaily,
 * qui reste disponible en secours pendant la bascule ; l'ancien cron hôte est
 * désactivé (commenté), pas supprimé.
 *
 *   php bin/console app:weather:aggregate
 */
class WeatherAggregateCommand extends Command
{
    protected static $defaultName = 'app:weather:aggregate';

    private WeatherDaily $weatherDaily;

    public function __construct(WeatherDaily $weatherDaily)
    {
        parent::__construct();
        $this->weatherDaily = $weatherDaily;
    }

    protected function configure(): void
    {
        $this->setDescription('Agrège weather_hwminutely en données journalières weather_daily (cron quotidien).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stamp = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        try {
            $count = $this->weatherDaily->generateMissingDaily();
        } catch (\Throwable $e) {
            $output->writeln(sprintf('[%s] app:weather:aggregate ERREUR : %s', $stamp, $e->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(sprintf('[%s] app:weather:aggregate : %d jour(s) généré(s).', $stamp, $count));

        return Command::SUCCESS;
    }
}
