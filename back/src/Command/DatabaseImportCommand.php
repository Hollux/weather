<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * php bin/console app:import-sql old_datas.sql --batch-size=1000
 * 
 * Importe un fichier SQL volumineux par batch, ignore les doublons sur ID
 */
class DatabaseImportCommand extends Command
{
    protected static $defaultName = 'app:import-sql';

    private $connection;

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    protected function configure()
    {
        $this
            ->setDescription('Importe un fichier SQL volumineux par batch, ignore les doublons sur ID')
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
        $insideInsert = false;

        $pdo->beginTransaction();

        while (($line = fgets($handle)) !== false) {
            $lineTrim = trim($line);

            // Ignorer lignes vides ou commentaires
            if ($lineTrim === '' || strpos($lineTrim, '--') === 0 || strpos($lineTrim, '/*') === 0) {
                continue;
            }

            // Saute ligne INSERT INTO car on ne veut que les tuples
            if (stripos($lineTrim, 'insert into') === 0) {
                continue;
            }

            // Nettoie la ligne (finissant par , ou ;)
            $cleaned = rtrim($lineTrim, ",;\r\n");

            if ($cleaned !== '') {
                $batch[] = $cleaned;
            }

            // Quand batch atteint la taille ou fin de fichier
            if (count($batch) >= $batchSize /* ou fin */) {
                if ($batch) {
                    $sql = "INSERT IGNORE INTO weather_hwminutely (id, dt, temp, pressure, humidity, uvi, wind_speed, wind_deg) VALUES " .
                        implode(',', $batch);
                    $pdo->exec($sql);
                    $output->writeln("Batch de " . count($batch) . " lignes inséré");
                    $batch = [];
                }
            }
        }


        $pdo->commit();
        fclose($handle);

        $output->writeln('<info>Import finalisé avec succès.</info>');
        return Command::SUCCESS;
    }
}
