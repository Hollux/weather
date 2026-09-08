<?php

namespace App\Command;

use App\Service\CRange\CRangeImportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Importe un export de la station CRange (Orégon WMR300) dans
 * weather_hwminutely_crange + weather_daily_crange. Accepte aussi bien le
 * texte UTF-16LE tabulé (export brut de la station, faussement nommé .xls)
 * que du XLSX/XLS réel (fichiers ré-enregistrés depuis Excel/LibreOffice,
 * cas de l'archive Drive "orégon années/") — voir CRangeFileReader.
 *
 * Ré-import idempotent : weather_hwminutely_crange a un index unique sur `dt`
 * (INSERT IGNORE), et weather_daily_crange sur `day` (ON DUPLICATE KEY UPDATE
 * qui ne touche jamais `sunshine_minutes`, alimenté séparément).
 */
class CRangeImportCommand extends Command
{
    protected static $defaultName = 'app:crange:import';

    public function __construct(private CRangeImportService $importService)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Importe un export de la station CRange dans weather_hwminutely_crange / weather_daily_crange.')
            ->addArgument('file', InputArgument::REQUIRED, "Chemin du fichier export CRange (texte tabulé ou XLSX/XLS)");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $input->getArgument('file');

        if (!is_file($filePath)) {
            $output->writeln("<error>Fichier introuvable : $filePath</error>");

            return Command::FAILURE;
        }

        ini_set('memory_limit', '1G');

        $result = $this->importService->importFile($filePath);

        $output->writeln(sprintf(
            '<info>Import terminé : %d lignes traitées, %d jours agrégés.</info>',
            $result['rows'],
            $result['days']
        ));

        return Command::SUCCESS;
    }
}
