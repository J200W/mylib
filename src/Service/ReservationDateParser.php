<?php

declare(strict_types=1);

namespace App\Service;

final class ReservationDateParser
{
    public const MAX_LOAN_DAYS = 21;

    /**
     * @return array{start: \DateTime, end: \DateTime}|array{error: string}
     */
    public static function parse(string $startRaw, string $endRaw): array
    {
        $start = \DateTime::createFromFormat('!Y-m-d', $startRaw);
        $end = \DateTime::createFromFormat('!Y-m-d', $endRaw);
        if ($start === false || $end === false) {
            return ['error' => 'Les dates indiquées ne sont pas valides.'];
        }
        $start->setTime(0, 0, 0);
        $end->setTime(0, 0, 0);
        $today = new \DateTime('today');
        if ($start < $today) {
            return ['error' => 'La date de début ne peut pas être dans le passé.'];
        }
        if ($end < $start) {
            return ['error' => 'La date de fin doit être au moins égale à la date de début.'];
        }
        if ($start->diff($end)->days > $this->MAX_LOAN_DAYS) {
            return ['error' => 'La durée d’emprunt ne peut pas dépasser 3 semaines (21 jours).'];
        }

        return ['start' => $start, 'end' => $end];
    }
}
