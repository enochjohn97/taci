<?php
// src/Repository/SaleRepository.php

namespace App\Repository;

use App\Entity\Sale;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SaleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sale::class);
    }

    public function findByCashier($cashier, $limit = 50)
    {
        return $this->createQueryBuilder('s')
            ->where('s.cashier = :cashier')
            ->setParameter('cashier', $cashier)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findDailySalesTotal(\DateTime $date)
    {
        $startOfDay = (clone $date)->setTime(0, 0, 0);
        $endOfDay = (clone $date)->setTime(23, 59, 59);

        return $this->createQueryBuilder('s')
            ->select('SUM(s.totalAmount) as total')
            ->where('s.createdAt >= :start')
            ->andWhere('s.createdAt <= :end')
            ->andWhere('s.status = :status')
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    public function findMonthlySalesTotal(\DateTime $date)
    {
        $startOfMonth = (clone $date)->setDate($date->format('Y'), $date->format('m'), 1)->setTime(0, 0, 0);
        $endOfMonth = (clone $startOfMonth)->add(new \DateInterval('P1M'))->sub(new \DateInterval('P1D'))->setTime(23, 59, 59);

        return $this->createQueryBuilder('s')
            ->select('SUM(s.totalAmount) as total')
            ->where('s.createdAt >= :start')
            ->andWhere('s.createdAt <= :end')
            ->andWhere('s.status = :status')
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    public function findByDateRange(\DateTime $start, \DateTime $end)
    {
        return $this->createQueryBuilder('s')
            ->where('s.createdAt >= :start')
            ->andWhere('s.createdAt <= :end')
            ->andWhere('s.status = :status')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', 'completed')
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
