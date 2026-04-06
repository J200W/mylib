<?php

namespace App\Repository;

use App\Entity\Book;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Demandes de réservation d’un utilisateur, livre et auteur chargés, plus récentes en premier.
     *
     * @return list<Reservation>
     */
    public function findForAccountByUser(User $user): array
    {
        /**
         * SQL Query :
         * SELECT * FROM reservation 
         * WHERE user_id = :user
         * ORDER BY date_start DESC, id DESC
         */

        return $this->createQueryBuilder('r')
            ->innerJoin('r.book', 'b')->addSelect('b')
            ->innerJoin('b.author', 'auth')->addSelect('auth')
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.date_start', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Demandes en attente de validation (statut 0 ou null), pour l’administration.
     *
     * @return list<Reservation>
     */
    public function findPendingForAdmin(): array
    {

        /**
         * SQL Query :
         * SELECT * FROM reservation 
         * WHERE (status IS NULL OR status = 0)
         * ORDER BY date_start ASC, id ASC
         */

        return $this->createQueryBuilder('r')
            ->innerJoin('r.book', 'b')->addSelect('b')
            ->innerJoin('b.author', 'auth')->addSelect('auth')
            ->innerJoin('r.user', 'u')->addSelect('u')
            ->andWhere('r.status IS NULL OR r.status = 0')
            ->orderBy('r.date_start', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Historique admin / bibliothécaire : toutes les réservations, usager et livre chargés, plus récentes en premier.
     *
     * @return list<Reservation>
     */
    public function findForAdminHistory(?User $filterUser = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->innerJoin('r.user', 'u')->addSelect('u')
            ->innerJoin('r.book', 'b')->addSelect('b')
            ->orderBy('r.id', 'DESC');

        if (null !== $filterUser) {
            $qb->andWhere('r.user = :user')
                ->setParameter('user', $filterUser);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Réservation « ouverte » pour ce couple utilisateur / livre : en attente ou prêt accepté (bloque une nouvelle demande).
     */
    public function findOpenReservationForUserAndBook(User $user, Book $book): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.book = :book')
            ->andWhere('r.status IS NULL OR r.status IN (0, 1)')
            ->setParameter('user', $user)
            ->setParameter('book', $book)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Emprunts acceptés (statut 1) dont la période chevauche le jour calendaire donné (encore « en cours » ce jour-là).
     *
     * @return list<Reservation>
     */
    public function findAcceptedActiveOnDate(Book $book, \DateTimeInterface $day): array
    {
        $dayStart = \DateTimeImmutable::createFromInterface($day)->setTime(0, 0, 0);
        $dayEnd = $dayStart->modify('+1 day');

        /**
         * SQL Query :
         * SELECT * FROM reservation 
         * WHERE book_id = :book AND status = 1 
         * AND date_start < :dayEnd 
         * AND date_end >= :dayStart
         * ORDER BY date_start DESC
         */

        return $this->createQueryBuilder('r')
            ->andWhere('r.book = :book')
            ->andWhere('r.status = 1')
            ->andWhere('r.date_start < :dayEnd AND r.date_end >= :dayStart')
            ->setParameter('book', $book)
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
            ->getQuery()
            ->getResult();
    }

    /**
     * L’utilisateur peut laisser un avis uniquement s’il a rendu le livre (statut 3).
     */
    public function userCanCommentOnBook(User $user, Book $book): bool
    {
        $count = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.user = :user')
            ->andWhere('r.book = :book')
            ->andWhere('r.status = 3')
            ->setParameter('user', $user)
            ->setParameter('book', $book)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
