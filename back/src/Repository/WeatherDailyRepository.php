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
