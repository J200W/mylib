<?php

namespace App\Service;

use App\Entity\Book;
use App\Repository\ReservationRepository;

/**
 * INFO (de ce que j'ai compris du TP) :
 * Une demande peut être faite dès aujourd’hui si stock > 0 ; 
 * si stock = 0, à partir du lendemain de la date de fin la plus proche 
 * parmi les prêts acceptés encore en cours aujourd’hui.
 */
final class BookBorrowEligibility
{
    private ReservationRepository $reservationRepository;
    public function __construct(
         ReservationRepository $reservationRepository,
    ) {
        $this->reservationRepository = $reservationRepository;
    }

    public function isBookBorrowableInPrinciple(Book $book): bool
    {
        $stock = $book->getStock();
        if (null === $stock || $stock < 0) {
            return false;
        }
        if ($stock > 0) {
            return true;
        }

        $today = new \DateTimeImmutable('today');

        return \count($this->reservationRepository->findAcceptedActiveOnDate($book, $today)) > 0;
    }

    public function earliestAllowedStartDate(Book $book): ?\DateTimeImmutable
    {
        if (!$this->isBookBorrowableInPrinciple($book)) {
            return null;
        }
        $stock = $book->getStock();
        if (null === $stock) {
            return null;
        }
        if ($stock > 0) {
            return new \DateTimeImmutable('today');
        }

        $today = new \DateTimeImmutable('today');
        $active = $this->reservationRepository->findAcceptedActiveOnDate($book, $today);
        $minEnd = null;
        foreach ($active as $r) {
            $e = $r->getDateEnd();
            if (!$e instanceof \DateTimeInterface) {
                continue;
            }
            $eDay = \DateTimeImmutable::createFromInterface($e)->setTime(0, 0, 0);
            if (null === $minEnd || $eDay < $minEnd) {
                $minEnd = $eDay;
            }
        }

        if (null === $minEnd) {
            return null;
        }

        return $minEnd->modify('+1 day');
    }

    public function validateRequestedPeriod(Book $book, \DateTimeInterface $start, \DateTimeInterface $end): ?string
    {
        $earliest = $this->earliestAllowedStartDate($book);
        if (null === $earliest) {
            return 'Ce livre n’est pas disponible à l’emprunt pour le moment.';
        }

        $startDay = \DateTimeImmutable::createFromInterface($start)->setTime(0, 0, 0);
        $endDay = \DateTimeImmutable::createFromInterface($end)->setTime(0, 0, 0);

        if ($startDay < $earliest) {
            return 'La première date possible pour une réservation est le '.$earliest->format('d/m/Y').'.';
        }
        if ($endDay < $startDay) {
            return 'La date de fin doit être postérieure ou égale à la date de début.';
        }

        $parsed = ReservationDateParser::parse(
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        );
        if (isset($parsed['error'])) {
            return $parsed['error'];
        }

        return null;
    }
}
