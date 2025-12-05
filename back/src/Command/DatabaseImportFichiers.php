<?php

namespace App\Command;

use App\Entity\WeatherDaily;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseImportFichiers extends Command
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
            ->setDescription('Importe tous les fichiers CSV dans /datas par batch et charge les données journalières.')
            ->addArgument('folder', InputArgument::REQUIRED, 'Répertoire des fichiers CSV à importer')
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
        $folderPath = $input->getArgument('folder');
        $batchSize = (int)$input->getOption('batch-size');

        // Vérifier si le répertoire existe
        if (!is_dir($folderPath)) {
            $output->writeln('<error>Le répertoire spécifié n\'existe pas</error>');
            return Command::FAILURE;
        }

        // Liste de tous les fichiers CSV dans le répertoire
        $csvFiles = glob($folderPath . '/*.csv');

        if (empty($csvFiles)) {
            $output->writeln('<error>Aucun fichier CSV trouvé dans le répertoire</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Fichiers CSV trouvés : </info>');
        foreach ($csvFiles as $file) {
            $output->writeln(" - " . basename($file));
        }

        // Traiter chaque fichier CSV
        foreach ($csvFiles as $filePath) {
            $this->importCsvFile($filePath, $batchSize, $output);
        }

        $output->writeln('<info>Import terminé pour tous les fichiers !</info>');
        return Command::SUCCESS;
    }

    private function importCsvFile(string $filePath, int $batchSize, OutputInterface $output): void
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $output->writeln("<error>Impossible d'ouvrir le fichier: $filePath</error>");
            return;
        }

        $batch = [];
        $dayStats = [];

        // Lire les lignes du fichier CSV
        while (($data = fgetcsv($handle, 0, ",")) !== false) {
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

            // Mettre à jour les pressions max/min
            $dayStats[$day]['pressure_max'] = max($dayStats[$day]['pressure_max'] ?? 0, (float)$pressure);
            $dayStats[$day]['pressure_min'] = min($dayStats[$day]['pressure_min'] ?? PHP_FLOAT_MAX, (float)$pressure);

            // Humidité max/min
            $dayStats[$day]['humidity_max'] = max($dayStats[$day]['humidity_max'] ?? 0, (float)$humidity);
            $dayStats[$day]['humidity_min'] = min($dayStats[$day]['humidity_min'] ?? PHP_FLOAT_MAX, (float)$humidity);

            // Mettre à jour les vitesses de vent max
            $dayStats[$day]['wind_speed_max'] = max($dayStats[$day]['wind_speed_max'] ?? 0, (float)$windSpeed);

            //UVI max
            $dayStats[$day]['uvi_max'] = max($dayStats[$day]['uvi_max'] ?? 0, (float)$luminosity);

            $dayStats[$day]['count']++;

            // Si on atteint la taille du batch, on insère les données
            if (count($batch) >= $batchSize) {
                $this->insertBatch($batch, $output);
                $batch = []; // Réinitialiser le batch
            }
        }

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
            // ajouter les min/max
            $weather->setPressureMax($stats['pressure_max']);
            $weather->setPressureMin($stats['pressure_min']);
            $weather->setHumidityMax($stats['humidity_max']);
            $weather->setHumidityMin($stats['humidity_min']);
            $weather->setWindSpeedMax($stats['wind_speed_max']);
            $weather->setUviAvg(round($stats['uvi'] / $stats['count'], 2));
            $weather->setUviMax($stats['uvi_max']);

            // Remplir le tableau de données avec la bonne clé pour correspondre à `toArray()`
            $weatherData = $weather->toArrayForImport();

            // Insertion dans la table weather_daily avec les bonnes colonnes
            $this->connection->insert('weather_daily', $weatherData);
        }

        $output->writeln("<info>Import terminé pour le fichier: $filePath</info>");
    }

    private function insertBatch(array $batch, OutputInterface $output): void
    {
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
