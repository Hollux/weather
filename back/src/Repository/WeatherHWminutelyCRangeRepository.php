<?php

namespace App\Repository;

use App\Entity\WeatherHWminutelyCRange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method WeatherHWminutelyCRange|null find($id, $lockMode = null, $lockVersion = null)
 * @method WeatherHWminutelyCRange|null findOneBy(array $criteria, array $orderBy = null)
 * @method WeatherHWminutelyCRange[]    findAll()
 * @method WeatherHWminutelyCRange[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WeatherHWminutelyCRangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeatherHWminutelyCRange::class);
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
}
