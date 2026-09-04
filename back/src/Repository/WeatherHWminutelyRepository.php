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

    /**
     * Jours (minuit UTC, en timestamp) présents dans weather_hwminutely mais pas
     * encore agrégés dans weather_daily.
     *
     * Le bucket de jour est calculé sans FROM_UNIXTIME (donc sans dépendre du
     * fuseau de la session MySQL) : FLOOR(dt / 86400) * 86400 = minuit UTC du jour
     * de la mesure. weather_daily.day suit la même convention (cf. createWeatherDaily).
     *
     * @return int[] timestamps de minuit UTC, ordre croissant
     */
    public function findDaysNotInDaily(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT DISTINCT FLOOR(h.dt / 86400) * 86400 AS day_epoch
            FROM weather_hwminutely h
            WHERE FLOOR(h.dt / 86400) * 86400 NOT IN (
                SELECT d.day FROM weather_daily d
            )
            ORDER BY day_epoch ASC
        ";

        return array_map('intval', $conn->executeQuery($sql)->fetchFirstColumn());
    }


    /**
     * Mesures d'un jour donné (borne [minuit UTC, minuit UTC + 24h[), via l'index sur dt.
     */
    public function findDayData(int $dayEpoch): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT *
            FROM weather_hwminutely
            WHERE dt >= :start AND dt < :end
            ORDER BY dt ASC
        ";

        return $conn->executeQuery($sql, [
            'start' => $dayEpoch,
            'end' => $dayEpoch + 86400,
        ])->fetchAllAssociative();
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
