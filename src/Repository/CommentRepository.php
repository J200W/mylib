<?php

namespace App\Repository;

use App\Entity\Book;
use App\Entity\Comment;
use App\Entity\User;
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

    public function findOneByUserAndBook(User $user, Book $book): ?Comment
    {
        return $this->findOneBy(['user' => $user, 'book' => $book]);
    }

    /**
     * @return list<Comment>
     */
    public function findPublishedForBookOrdered(Book $book, string $sort): array
    {
        $qb = $this->createQueryBuilder('c')
            ->innerJoin('c.user', 'u')->addSelect('u')
            ->andWhere('c.book = :book')
            ->andWhere('c.isActive = true')
            ->setParameter('book', $book);

        match ($sort) {
            'oldest' => $qb->orderBy('c.createdAt', 'ASC'),
            'rating_high' => $qb->orderBy('c.rating', 'DESC')->addOrderBy('c.createdAt', 'DESC'),
            'rating_low' => $qb->orderBy('c.rating', 'ASC')->addOrderBy('c.createdAt', 'DESC'),
            default => $qb->orderBy('c.createdAt', 'DESC'),
        };

        return $qb->getQuery()->getResult();
    }

    /**
     * Avis masqués (modération), pour réactivation par un administrateur.
     *
     * @return list<Comment>
     */
    public function findHiddenForBookOrdered(Book $book, string $sort): array
    {
        $qb = $this->createQueryBuilder('c')
            ->innerJoin('c.user', 'u')->addSelect('u')
            ->andWhere('c.book = :book')
            ->andWhere('c.isActive = false')
            ->setParameter('book', $book);

        match ($sort) {
            'oldest' => $qb->orderBy('c.createdAt', 'ASC'),
            'rating_high' => $qb->orderBy('c.rating', 'DESC')->addOrderBy('c.createdAt', 'DESC'),
            'rating_low' => $qb->orderBy('c.rating', 'ASC')->addOrderBy('c.createdAt', 'DESC'),
            default => $qb->orderBy('c.createdAt', 'DESC'),
        };

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array{count: int, average: float|null}
     */
    public function getPublishedStatsForBook(Book $book): array
    {
        $row = $this->createQueryBuilder('c')
            ->select('COUNT(c.id) AS cnt')
            ->addSelect('AVG(c.rating) AS avgRating')
            ->andWhere('c.book = :book')
            ->andWhere('c.isActive = true')
            ->setParameter('book', $book)
            ->getQuery()
            ->getSingleResult();

        $cnt = (int) ($row['cnt'] ?? 0);
        $avg = $row['avgRating'] ?? null;

        return [
            'count' => $cnt,
            'average' => null !== $avg && '' !== $avg ? round((float) $avg, 1) : null,
        ];
    }
}
