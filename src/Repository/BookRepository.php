<?php

namespace App\Repository;

use App\Entity\Book;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Book>
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    /**
     * @param list<int> $categoryIds
     * @param list<int> $languageIds
     *
     * @return list<Book>
     */
    public function findForCatalogue(
        string $search = '',
        array $categoryIds = [],
        array $languageIds = [],
        string $genreSearch = '',
        string $sort = 'title',
        bool $inStockOnly = false,
    ): array {
        $qb = $this->createQueryBuilder('b')
            ->distinct()
            ->innerJoin('b.author', 'auth')->addSelect('auth')
            ->innerJoin('b.language', 'lang')->addSelect('lang')
            ->innerJoin('b.category', 'cat')->addSelect('cat');

        if ($search !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    'b.title LIKE :q',
                    'b.description LIKE :q',
                    'CONCAT(auth.firstname, :space, auth.lastname) LIKE :q',
                ),
            )
                ->setParameter('q', '%'.$search.'%')
                ->setParameter('space', ' ');
        }

        if ($categoryIds !== []) {
            $qb->andWhere('cat.id IN (:catIds)')
                ->setParameter('catIds', $categoryIds);
        }

        if ($languageIds !== []) {
            $qb->andWhere('lang.id IN (:langIds)')
                ->setParameter('langIds', $languageIds);
        }

        if ($genreSearch !== '') {
            $qb->andWhere('LOWER(cat.name) LIKE :genreQ')
                ->setParameter('genreQ', '%'.mb_strtolower($genreSearch).'%');
        }

        if ($inStockOnly) {
            $qb->andWhere('b.stock IS NOT NULL AND b.stock > 0');
        }

        match ($sort) {
            'title_desc' => $qb->orderBy('b.title', 'DESC'),
            'stock' => $qb->orderBy('b.stock', 'DESC')->addOrderBy('b.title', 'ASC'),
            'random' => $qb->orderBy('b.id', 'ASC'),
            default => $qb->orderBy('b.title', 'ASC'),
        };

        /** @var list<Book> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Livres marqués favoris par l’utilisateur, tri par titre, avec auteur / langue / catégories chargés.
     *
     * @return list<Book>
     */
    public function findFavoriteBooksForUser(User $user, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->distinct()
            ->innerJoin('b.favorites', 'fav')
            ->innerJoin('b.author', 'auth')->addSelect('auth')
            ->innerJoin('b.language', 'lang')->addSelect('lang')
            ->innerJoin('b.category', 'cat')->addSelect('cat')
            ->andWhere('fav.user = :user')
            ->setParameter('user', $user)
            ->orderBy('b.title', 'ASC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        /** @var list<Book> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function countFavoriteBooksForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(DISTINCT b.id)')
            ->innerJoin('b.favorites', 'fav')
            ->andWhere('fav.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
