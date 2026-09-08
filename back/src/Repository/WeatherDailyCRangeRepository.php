<?php

namespace App\Repository;

use App\Entity\WeatherDailyCRange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WeatherDailyCRange>
 *
 * @method WeatherDailyCRange|null find($id, $lockMode = null, $lockVersion = null)
 * @method WeatherDailyCRange|null findOneBy(array $criteria, array $orderBy = null)
 * @method WeatherDailyCRange[]    findAll()
 * @method WeatherDailyCRange[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WeatherDailyCRangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeatherDailyCRange::class);
    }

    /**
     * Récupère les enregistrements WeatherDailyCRange entre deux timestamps (bornes incluses).
     *
     * @return WeatherDailyCRange[]
     */
    public function getDailyWithMinMax($min, $max)
    {
        return $this->createQueryBuilder('w')
            ->select('w')
            ->setParameter('min', $min)
            ->setParameter('max', $max)
            ->Where('w.day >= :min')
            ->AndWhere('w.day <= :max')
            ->orderBy('w.day', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return WeatherDailyCRange[] recupere les données des années passées en paramètre
     */
    public function getAllInYears($years)
    {
        $resultsByYear = [];

        foreach ($years as $year) {
            // Timestamps Unix pour integer column (UTC)
            $start = gmmktime(0, 0, 0, 1, 1, $year);           // 1er janv 00:00 UTC
            $end = gmmktime(23, 59, 59, 12, 31, $year);        // 31 déc 23:59 UTC

            $qb = $this->createQueryBuilder('wd')
                ->where('wd.day BETWEEN :start AND :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->orderBy('wd.day', 'ASC');  // Chronologique dans l'année

            $resultsByYear[(int)$year] = $qb->getQuery()->getResult();
        }

        return $resultsByYear;
    }

    /**
     * Les N derniers jours (aujourd'hui inclus), du plus récent au plus ancien,
     * pour la page "Luminosité Journalière". Retourne les jours existants en base
     * uniquement (les jours sans ligne du tout sont à compléter côté appelant).
     *
     * @return WeatherDailyCRange[]
     */
    public function findLastNDays(int $n): array
    {
        $todayEpoch = intdiv(time(), 86400) * 86400;
        $sinceEpoch = $todayEpoch - ($n - 1) * 86400;

        return $this->createQueryBuilder('w')
            ->select('w')
            ->setParameter('since', $sinceEpoch)
            ->Where('w.day >= :since')
            ->orderBy('w.day', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
