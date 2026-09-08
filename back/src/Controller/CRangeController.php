<?php

namespace App\Controller;

use App\Entity\WeatherDailyCRange;
use App\Repository\WeatherDailyCRangeRepository;
use App\Service\CRange\CRangeDriveClient;
use App\Service\CRange\CRangeSyncService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Saisie de la "Luminosité Journalière" (durée d'ensoleillement du jour) pour la
 * station CRange, réservée à ROLE_CRANGE via security.yaml (^/crange).
 * Complète weather_daily_crange.sunshine_minutes, que l'import automatique
 * (CRangeImportCommand) ne peut pas renseigner faute de capteur UV/luminosité.
 */
class CRangeController extends AbstractController
{
    /**
     * Dossier local temporaire pour les fichiers téléchargés depuis Drive le
     * temps d'un run de sync() — vidé systématiquement à la fin (voir sync()) :
     * on ne conserve jamais ces fichiers, weather_crange_sync_state suffit à
     * savoir ce qui a déjà été importé.
     */
    private const DRIVE_MIRROR_DIR = '/app/datas/drive_oregon';

    public function __construct(
        private WeatherDailyCRangeRepository $dailyRepository,
        private EntityManagerInterface $entityManager,
        private CRangeDriveClient $driveClient,
        private CRangeSyncService $syncService,
        private Connection $connection
    ) {
    }

    /**
     * @Route("/crange/luminosity/{days}", defaults={"days"=10}, requirements={"days"="\d+"}, methods={"GET"})
     */
    public function listLuminosity(int $days): JsonResponse
    {
        $days = max(1, min($days, 60));
        $todayEpoch = intdiv(time(), 86400) * 86400;

        $existing = [];
        foreach ($this->dailyRepository->findLastNDays($days) as $entity) {
            $existing[$entity->getDay()] = $entity;
        }

        $result = [];
        for ($i = 0; $i < $days; $i++) {
            $dayEpoch = $todayEpoch - $i * 86400;
            $entity = $existing[$dayEpoch] ?? null;
            $minutes = $entity?->getSunshineMinutes();

            $result[] = [
                'day' => gmdate('Y-m-d', $dayEpoch),
                'hours' => $minutes !== null ? intdiv($minutes, 60) : null,
                'minutes' => $minutes !== null ? $minutes % 60 : null,
                'filled' => $minutes !== null,
            ];
        }

        return new JsonResponse($result);
    }

    /**
     * @Route("/crange/luminosity", methods={"POST"})
     */
    public function saveLuminosity(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $dayStr = $data['day'] ?? null;
        $hours = $data['hours'] ?? null;
        $minutes = $data['minutes'] ?? null;

        if (!is_string($dayStr) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayStr)) {
            return new JsonResponse(['error' => 'Jour invalide'], Response::HTTP_BAD_REQUEST);
        }
        if (!is_int($hours) || $hours < 0 || $hours > 24 || !is_int($minutes) || $minutes < 0 || $minutes > 60) {
            return new JsonResponse(['error' => 'Durée invalide (heures 0-24, minutes 0-60)'], Response::HTTP_BAD_REQUEST);
        }

        $dayEpoch = strtotime($dayStr . ' UTC');

        $entity = $this->entityManager->getRepository(WeatherDailyCRange::class)->findOneBy(['day' => $dayEpoch]);

        if (!$entity) {
            $entity = new WeatherDailyCRange();
            $entity->setDay($dayEpoch);
        }

        $entity->setSunshineMinutes($hours * 60 + $minutes);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return new JsonResponse([
            'day' => $dayStr,
            'hours' => $hours,
            'minutes' => $minutes,
            'filled' => true,
        ]);
    }

    /**
     * Déclenche depuis le site (bouton "Maj CRange", ROLE_CRANGE) la synchro
     * du dossier Drive "Orégon sauve graph/" : télécharge uniquement les
     * fichiers nouveaux ou modifiés depuis le dernier run (comparé à
     * weather_crange_sync_state), les importe via CRangeSyncService, puis les
     * supprime du disque — on ne garde jamais de copie locale des exports.
     *
     * @Route("/crange/sync", methods={"POST"})
     */
    public function sync(): JsonResponse
    {
        set_time_limit(0);
        ini_set('memory_limit', '1G');

        $dir = self::DRIVE_MIRROR_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return new JsonResponse(['error' => "Impossible de créer le dossier temporaire $dir"], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $driveFiles = $this->driveClient->listFiles();
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Connexion à Google Drive impossible : ' . $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        // Le dossier peut contenir autre chose qu'un export (raccourci .lnk...).
        $driveFiles = array_values(array_filter($driveFiles, fn ($f) => preg_match('/\.xlsx?$/i', $f['name'])));

        if (empty($driveFiles)) {
            return new JsonResponse(['filesChecked' => 0, 'filesUpdated' => [], 'totalRowsInserted' => 0, 'daysRecomputed' => 0]);
        }

        // On ne télécharge (puis n'importe) que les fichiers dont le nom n'a
        // jamais été lu : CRangeSyncService applique le même garde-fou et
        // ignorerait de toute façon un nom déjà présent dans la table.
        $knownFilenames = array_flip($this->connection->fetchFirstColumn(
            'SELECT filename FROM weather_crange_sync_state WHERE filename IN (' .
            implode(',', array_fill(0, count($driveFiles), '?')) . ')',
            array_map(fn ($f) => $f['name'], $driveFiles)
        ));

        $toDownload = array_filter($driveFiles, fn ($f) => !isset($knownFilenames[$f['name']]));

        try {
            foreach ($toDownload as $f) {
                $localPath = $dir . '/' . $f['name'];
                $this->driveClient->downloadFile($f['id'], $localPath);
                touch($localPath, $f['modifiedAt']->getTimestamp());
            }

            $result = $this->syncService->syncDirectory($dir);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => "Échec de l'import : " . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        } finally {
            foreach (glob($dir . '/*') ?: [] as $leftover) {
                @unlink($leftover);
            }
        }

        $updated = array_values(array_filter($result['files'], fn ($f) => $f['status'] !== 'unchanged'));

        return new JsonResponse([
            'filesChecked' => count($driveFiles),
            'filesUpdated' => array_map(fn ($f) => ['filename' => $f['filename'], 'rowsInserted' => $f['rowsInserted']], $updated),
            'totalRowsInserted' => $result['totalRowsInserted'],
            'daysRecomputed' => $result['daysRecomputed'],
        ]);
    }
}
