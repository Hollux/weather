<?php

namespace App\Repository;

use App\Entity\WeatherVille;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method WeatherVille|null find($id, $lockMode = null, $lockVersion = null)
 * @method WeatherVille|null findOneBy(array $criteria, array $orderBy = null)
 * @method WeatherVille[]    findAll()
 * @method WeatherVille[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WeatherVilleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeatherVille::class);
    }

    // /**
    //  * @return WeatherVille[] Returns an array of WeatherVille objects
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
    public function findOneBySomeField($value): ?WeatherVille
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
