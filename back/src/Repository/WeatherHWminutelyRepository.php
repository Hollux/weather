<?php

namespace App\Repository;

use App\Entity\WeatherHWminutely;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method WeatherHWminutely|null find($id, $lockMode = null, $lockVersion = null)
 * @method WeatherHWminutely|null findOneBy(array $criteria, array $orderBy = null)
 * @method WeatherHWminutely[]    findAll()
 * @method WeatherHWminutely[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WeatherHWminutelyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeatherHWminutely::class);
    }

    public function getAllInDtMinMax($min, $max)
    {
        return $this->createQueryBuilder('w')
            ->select('w')
            ->setParameter('min', $min)
            ->setParameter('max', $max)
            ->Where('w.dt >= :min')
            ->AndWhere('w.dt <= :max')
            ->orderBy('w.dt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findDaysNotInDaily(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT DISTINCT DATE(FROM_UNIXTIME(dt)) AS day
            FROM weather_hwminutely
            WHERE DATE(FROM_UNIXTIME(dt)) NOT IN (
                SELECT day FROM weather_daily
            )
            ORDER BY day ASC
        ";

        return $conn->executeQuery($sql)->fetchFirstColumn();
    }


    public function findDayData(string $day): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT *
            FROM weather_hwminutely
            WHERE DATE(FROM_UNIXTIME(dt)) = :day
            ORDER BY dt ASC
        ";

        return $conn->executeQuery($sql, ['day' => $day])->fetchAllAssociative();
    }




    // /**
    //  * @return WeatherHWminutely[] Returns an array of WeatherHWminutely objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('w.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?WeatherHWminutely
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
