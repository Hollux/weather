<?php

namespace App\Command;

use App\Entity\WeatherDaily;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseImportFichier extends Command
{
    protected static $defaultName = 'app:import-files';

    private Connection $connection;

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    protected function configure()
    {
        $this
            ->setDescription('Importe des fichiers CSV par batch et charge les données journalières.')
            ->addArgument('file', InputArgument::REQUIRED, 'Chemin du fichier CSV à importer')
            ->addOption(
                'batch-size',
                null,
                InputOption::VALUE_REQUIRED,
                'Nombre de lignes par batch',
                1000
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<error>DATABASE IMPORT FICHIER</error>');


        $filePath = $input->getArgument('file');
        $batchSize = (int)$input->getOption('batch-size');

        if (!file_exists($filePath)) {
            $output->writeln('<error>Le fichier spécifié n\'existe pas</error>');
            return Command::FAILURE;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $output->writeln('<error>Impossible d\'ouvrir le fichier</error>');
            return Command::FAILURE;
        }

        $batch = [];
        $dayStats = [];
        $count = 0;

        // Lire les lignes du fichier CSV
        while (($data = fgetcsv($handle, 0, ",")) !== false) {
            $count++;
            // Sauter les lignes 2 premières lignes d'en-tête
            if ($data[0] === 'DATE' || $data[0] === '') {
                continue;
            }

            // Nettoyer les espaces avant et après
            $dateStr = trim($data[0]) . ' ' . trim($data[1]); // On assemble les 2 parties en une seule chaîne
            // Convertir la chaîne en objet DateTime avec le bon format
            $date = \DateTime::createFromFormat('d.m.Y H:i', $dateStr);
            $timestamp = $date ? $date->getTimestamp() : null;
            $temp = str_replace(',', '.', $data[2]);
            $humidity = str_replace(',', '.', $data[3]);
            $pressure = str_replace(',', '.', $data[12]);
            $windSpeed = str_replace(',', '.', $data[9]);
            $windDeg = str_replace(',', '.', $data[11]);
            $luminosity = str_replace(',', '.', $data[13]);

            // Convertir les valeurs
            $day = date('Y-m-d', $timestamp); // On récupère juste le jour (année-mois-jour)

            // Ajouter la donnée à la statistique journalière
            if (!isset($dayStats[$day])) {
                $dayStats[$day] = [
                    'dt' => $timestamp,
                    'temp_sum' => 0,
                    'temp_max' => (float)$temp,
                    'temp_min' => (float)$temp,
                    'humidity_sum' => 0,
                    'pressure_sum' => 0,
                    'wind_speed_sum' => 0,
                    'wind_deg_sum' => 0,
                    'uvi' => 0,
                    'count' => 0,
                ];
            }

            // Ajouter les autres statistiques pour le jour
            $dayStats[$day]['dt'] = $timestamp;
            $dayStats[$day]['temp_sum'] += (float)$temp;
            $dayStats[$day]['humidity_sum'] += (float)$humidity;
            $dayStats[$day]['pressure_sum'] += (float)$pressure;
            $dayStats[$day]['wind_speed_sum'] += (float)$windSpeed;
            $dayStats[$day]['wind_deg_sum'] += (float)$windDeg;
            $dayStats[$day]['uvi'] += (float)$luminosity;

            // Mettre à jour les max/min pour la température
            $dayStats[$day]['temp_max'] = max($dayStats[$day]['temp_max'], (float)$temp);
            $dayStats[$day]['temp_min'] = min($dayStats[$day]['temp_min'], (float)$temp);

            $dayStats[$day]['count']++;

            // Si on atteint la taille du batch, on insère les données
            if (count($batch) >= $batchSize) {
                $this->insertBatch($batch, $output);
                $batch = []; // Réinitialiser le batch
            }
        }
        //dd('count final', $count);

        // Traiter le dernier batch
        if (count($batch) > 0) {
            $this->insertBatch($batch, $output);
        }

        fclose($handle);

        // Ajouter les données journalières à la base de données
        foreach ($dayStats as $day => $stats) {
            $weather = new WeatherDaily();
            $weather->setDay((int)$stats['dt']);
            $weather->setTempAvg(round($stats['temp_sum'] / $stats['count'], 2));
            $weather->setTempMax($stats['temp_max']);
            $weather->setTempMin($stats['temp_min']);
            $weather->setHumidityAvg(round($stats['humidity_sum'] / $stats['count'], 2));
            $weather->setPressureAvg(round($stats['pressure_sum'] / $stats['count'], 2));
            $weather->setWindSpeedAvg(round($stats['wind_speed_sum'] / $stats['count'], 2));
            $weather->setWindDegAvg(round($stats['wind_deg_sum'] / $stats['count'], 2));

            // Remplir le tableau de données avec la bonne clé pour correspondre à `toArray()`
            $weatherData = $weather->toArrayForImport();

            // Insertion dans la table weather_daily avec les bonnes colonnes
            $this->connection->insert('weather_daily', $weatherData);
        }

        $output->writeln('<info>Import terminé !</info>');

        return Command::SUCCESS;
    }

    private function insertBatch(array $batch, OutputInterface $output): void
    {
        dd('dd batch', $batch);

        if (empty($batch)) {
            return;
        }

        // Préparer la requête SQL
        $sql = "INSERT IGNORE INTO weather_hwminutely
                (day, temp, pressure, humidity, wind_speed, wind_deg)
                VALUES " . implode(',', $batch);



        try {
            $this->connection->executeQuery($sql);
            $output->writeln("Batch de " . count($batch) . " lignes inséré");
        } catch (\PDOException $e) {
            $output->writeln("<error>Erreur SQL sur le batch : " . $e->getMessage() . "</error>");
        }
    }
}
