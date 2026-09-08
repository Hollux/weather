<?php

namespace App\Command;

use App\Service\CRange\CRangeImportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Backfill initial : importe tous les fichiers d'un dossier (typiquement une
 * copie locale de "orégon années/" récupérée depuis Drive via rclone côté
 * hôte, voir scripts/crange-sync/) dans weather_hwminutely_crange /
 * weather_daily_crange. Idempotent (cf. CRangeImportCommand) : peut être
 * relancé sans risque, y compris sur des fichiers qui se recouvrent.
 */
class CRangeImportYearsCommand extends Command
{
    protected static $defaultName = 'app:crange:import-years';

    public function __construct(private CRangeImportService $importService)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription("Importe tous les fichiers d'export CRange d'un dossier local (backfill 'orégon années/').")
            ->addArgument('dir', InputArgument::REQUIRED, 'Dossier local contenant les fichiers export (.xls/.xlsx)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = rtrim($input->getArgument('dir'), '/');

        if (!is_dir($dir)) {
            $output->writeln("<error>Dossier introuvable : $dir</error>");

            return Command::FAILURE;
        }

        ini_set('memory_limit', '2G');

        $files = glob($dir . '/*.xls*');
        sort($files);

        if (empty($files)) {
            $output->writeln("<comment>Aucun fichier .xls/.xlsx dans $dir</comment>");

            return Command::SUCCESS;
        }

        $totalRows = 0;
        $totalDays = 0;
        foreach ($files as $file) {
            $output->write('- ' . basename($file) . ' ... ');
            $result = $this->importService->importFile($file);
            $output->writeln(sprintf('%d lignes, %d jours', $result['rows'], $result['days']));
            $totalRows += $result['rows'];
            $totalDays += $result['days'];
        }

        $output->writeln(sprintf(
            '<info>Backfill terminé : %d fichier(s), %d lignes traitées, %d jours touchés au total.</info>',
            count($files),
            $totalRows,
            $totalDays
        ));

        return Command::SUCCESS;
    }
}
