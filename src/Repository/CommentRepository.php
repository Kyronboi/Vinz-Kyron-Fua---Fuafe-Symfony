<?php

namespace App\Repository;

use App\Entity\Comment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * Eagerly loads customer to eliminate N+1 queries.
     * @return Comment[]
     */
    public function findLatestVisible(int $limit = 6): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('u')
            ->leftJoin('c.customer', 'u')
            ->where('c.isHidden = false')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Eagerly loads customer to eliminate N+1 queries on the comments page.
     * @return Comment[]
     */
    public function findAllVisible(): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('u')
            ->leftJoin('c.customer', 'u')
            ->where('c.isHidden = false')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return Comment[] Returns an array of Comment objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Comment
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
