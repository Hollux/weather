<?php

namespace App\Command;

use App\Service\CRange\CRangeImportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Backfill depuis un miroir local du dossier Drive "orégon mois/" (fichiers
 * mensuels, 2014-09 → aujourd'hui). Sert notamment à renseigner les colonnes
 * pluie (rain_rate / rain_hourly / rain_accumulated puis les agrégats
 * journaliers rain_*) sur des lignes déjà en base.
 *
 * Contrairement à app:crange:import-years, l'agrégat journalier est recalculé
 * depuis la base complète après insertion (CRangeImportService::importDirectory)
 * : les jours à cheval sur une frontière de mois ne sont pas écrasés par le
 * bout de fichier voisin. Idempotent, relançable.
 */
class CRangeImportMonthsCommand extends Command
{
    protected static $defaultName = 'app:crange:import-months';

    public function __construct(private CRangeImportService $importService)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription("Backfill CRange depuis un miroir local de 'orégon mois/' (insère puis recalcule chaque jour touché).")
            ->addArgument('dir', InputArgument::REQUIRED, 'Dossier local contenant les fichiers export mensuels (.xls/.xlsx), récursif non géré');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = rtrim($input->getArgument('dir'), '/');

        if (!is_dir($dir)) {
            $output->writeln("<error>Dossier introuvable : $dir</error>");

            return Command::FAILURE;
        }

        ini_set('memory_limit', '2G');

        $result = $this->importService->importDirectory(
            $dir,
            fn (string $name, int $rows) => $output->writeln(sprintf('- %s : %d ligne(s)', $name, $rows))
        );

        if ($result['files'] === 0) {
            $output->writeln("<comment>Aucun fichier .xls/.xlsx dans $dir</comment>");

            return Command::SUCCESS;
        }

        $output->writeln(sprintf(
            '<info>Backfill terminé : %d fichier(s), %d ligne(s) minutely, %d jour(s) recalculé(s).</info>',
            $result['files'],
            $result['rows'],
            $result['days']
        ));

        return Command::SUCCESS;
    }
}
