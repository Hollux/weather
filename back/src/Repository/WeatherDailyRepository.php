<?php

namespace App\Repository;

use App\Entity\WeatherDaily;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WeatherDaily>
 *
 * @method WeatherDaily|null find($id, $lockMode = null, $lockVersion = null)
 * @method WeatherDaily|null findOneBy(array $criteria, array $orderBy = null)
 * @method WeatherDaily[]    findAll()
 * @method WeatherDaily[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WeatherDailyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeatherDaily::class);
    }

    /**
     * @return WeatherDaily[] recupere les données des années passées en paramètre
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





    //    /**
    //     * @return WeatherDaily[] Returns an array of WeatherDaily objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('w')
    //            ->andWhere('w.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('w.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?WeatherDaily
    //    {
    //        return $this->createQueryBuilder('w')
    //            ->andWhere('w.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
