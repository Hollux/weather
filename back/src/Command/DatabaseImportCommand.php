<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseImportCommand extends Command
{
    protected static $defaultName = 'app:import-sql';

    private Connection $connection;

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    protected function configure()
    {
        $this
            ->setDescription('Importe un fichier SQL volumineux par batch, ignore les doublons et ne charge qu’une seule fois.')
            ->addArgument('file', InputArgument::REQUIRED, 'Chemin du fichier SQL à importer')
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
        $table = "weather_hwminutely";

        // Vérifie si la table contient déjà des données
        $existingCount = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM $table");

        if ($existingCount > 0) {
            $output->writeln("<comment>Import ignoré : la table contient déjà $existingCount lignes.</comment>");
            return Command::SUCCESS;
        }

        $output->writeln("<info>Table vide → Import SQL nécessaire</info>");

        $file = $input->getArgument('file');
        $batchSize = (int)$input->getOption('batch-size');

        if (!file_exists($file) || !is_readable($file)) {
            $output->writeln("<error>Fichier introuvable ou illisible : $file</error>");
            return Command::FAILURE;
        }

        $pdo = $this->connection->getNativeConnection();
        $output->writeln("<info>Import en cours depuis $file par batch de $batchSize lignes...</info>");

        $handle = fopen($file, "r");
        if (!$handle) {
            $output->writeln("<error>Impossible d'ouvrir le fichier $file</error>");
            return Command::FAILURE;
        }

        $batch = [];

        while (($line = fgets($handle)) !== false) {
            $lineTrim = trim($line);

            // Ignore les commentaires, lignes vides et COMMIT
            if (
                $lineTrim === '' ||
                str_starts_with($lineTrim, '--') ||
                str_starts_with($lineTrim, '/*') ||
                strtoupper($lineTrim) === 'COMMIT;'
            ) {
                continue;
            }

            // Ignore la ligne INSERT INTO du fichier SQL
            if (stripos($lineTrim, 'insert into') === 0) {
                continue;
            }

            $cleaned = rtrim($lineTrim, ",;\r\n");

            if ($cleaned !== '') {
                $batch[] = $cleaned;
            }

            if (count($batch) >= $batchSize) {
                $this->insertBatch($pdo, $batch, $output);
                $batch = [];
            }
        }

        // Dernier batch
        if (!empty($batch)) {
            $this->insertBatch($pdo, $batch, $output);
        }

        fclose($handle);
        $output->writeln('<info>Import finalisé avec succès.</info>');

        return Command::SUCCESS;
    }

    private function insertBatch($pdo, array $batch, OutputInterface $output): void
    {
        if (empty($batch)) {
            return;
        }

        $sql = "INSERT IGNORE INTO weather_hwminutely 
                (id, dt, temp, pressure, humidity, uvi, wind_speed, wind_deg, wind_speed_kmh)
                VALUES " . implode(',', $batch);

        try {
            $pdo->exec($sql);
            $output->writeln("Batch de " . count($batch) . " lignes inséré");
        } catch (\PDOException $e) {
            $output->writeln("<error>Erreur SQL sur le batch : " . $e->getMessage() . "</error>");
        }
    }
}
