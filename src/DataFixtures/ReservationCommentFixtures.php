<?php

namespace App\DataFixtures;

use App\Entity\Book;
use App\Entity\Comment;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Réservations (démo) + 3 avis par livre (24 commentaires), avec prêts rendus pour l’éligibilité.
 */
final class ReservationCommentFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [CatalogFixtures::class, UserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $today = new \DateTimeImmutable('today');
        $toDt = static fn (\DateTimeImmutable $d): \DateTime => \DateTime::createFromImmutable($d);

        foreach ($this->returnedPairsForComments() as [$userKey, $bookId, $startOff, $endOff]) {
            $r = (new Reservation())
                ->setUser($this->userRef($userKey))
                ->setBook($this->bookRef($bookId))
                ->setStatus(3)
                ->setDateStart($toDt($today->modify($startOff)))
                ->setDateEnd($toDt($today->modify($endOff)));
            $manager->persist($r);
        }

        foreach ($this->extraReservations($today, $toDt) as $res) {
            $manager->persist($res);
        }

        foreach ($this->reviewsThreePerBook() as [$bookId, $userKey, $rating, $createdOff, $text]) {
            $c = (new Comment())
                ->setUser($this->userRef($userKey))
                ->setBook($this->bookRef($bookId))
                ->setText($text)
                ->setRating($rating)
                ->setIsActive(true)
                ->setCreatedAt($today->modify($createdOff));
            $manager->persist($c);
        }

        $manager->flush();
    }

    private function userRef(string $key): User
    {
        return $this->getReference(UserFixtures::REF_USER_PREFIX.$key, User::class);
    }

    private function bookRef(int $id): Book
    {
        return $this->getReference(CatalogFixtures::REF_BOOK_PREFIX.$id, Book::class);
    }

    /**
     * Un prêt rendu par couple (usager, livre) utilisé pour un commentaire.
     *
     * @return list<array{0: string, 1: int, 2: string, 3: string}>
     */
    private function returnedPairsForComments(): array
    {
        $pairs = [];
        foreach ($this->commentAuthorsByBook() as $bookId => $userKeys) {
            foreach ($userKeys as $i => $userKey) {
                $base = -80 - $bookId * 3 - $i;
                $pairs[] = [
                    $userKey,
                    $bookId,
                    $base.' days',
                    ($base + 14).' days',
                ];
            }
        }

        return $pairs;
    }

    /**
     * @return array<int, list<string>>
     */
    private function commentAuthorsByBook(): array
    {
        return [
            1 => ['user', 'user1', 'user2'],
            2 => ['user3', 'user4', 'user5'],
            3 => ['user6', 'user7', 'user8'],
            4 => ['user9', 'user10', 'user11'],
            5 => ['user12', 'user13', 'user14'],
            6 => ['user15', 'user16', 'user17'],
            7 => ['user', 'user3', 'user6'],
            8 => ['user9', 'user12', 'user15'],
        ];
    }

    /**
     * @return list<Reservation>
     */
    private function extraReservations(\DateTimeImmutable $today, \Closure $toDt): array
    {
        $out = [];

        $out[] = (new Reservation())
            ->setUser($this->userRef('user'))
            ->setBook($this->bookRef(3))
            ->setStatus(0)
            ->setDateStart($toDt($today->modify('+10 days')))
            ->setDateEnd($toDt($today->modify('+24 days')));

        $out[] = (new Reservation())
            ->setUser($this->userRef('user1'))
            ->setBook($this->bookRef(2))
            ->setStatus(0)
            ->setDateStart($toDt($today->modify('+5 days')))
            ->setDateEnd($toDt($today->modify('+19 days')));

        $out[] = (new Reservation())
            ->setUser($this->userRef('user4'))
            ->setBook($this->bookRef(5))
            ->setStatus(0)
            ->setDateStart($toDt($today->modify('+3 days')))
            ->setDateEnd($toDt($today->modify('+17 days')));

        $out[] = (new Reservation())
            ->setUser($this->userRef('user2'))
            ->setBook($this->bookRef(6))
            ->setStatus(1)
            ->setDateStart($toDt($today->modify('-5 days')))
            ->setDateEnd($toDt($today->modify('+12 days')));

        $out[] = (new Reservation())
            ->setUser($this->userRef('user5'))
            ->setBook($this->bookRef(8))
            ->setStatus(1)
            ->setDateStart($toDt($today->modify('-2 days')))
            ->setDateEnd($toDt($today->modify('+14 days')));

        $out[] = (new Reservation())
            ->setUser($this->userRef('librarian'))
            ->setBook($this->bookRef(4))
            ->setStatus(1)
            ->setDateStart($toDt($today->modify('-1 day')))
            ->setDateEnd($toDt($today->modify('+18 days')));

        $out[] = (new Reservation())
            ->setUser($this->userRef('user3'))
            ->setBook($this->bookRef(1))
            ->setStatus(2)
            ->setDateStart($toDt($today->modify('+7 days')))
            ->setDateEnd($toDt($today->modify('+21 days')));

        $out[] = (new Reservation())
            ->setUser($this->userRef('admin'))
            ->setBook($this->bookRef(2))
            ->setStatus(3)
            ->setDateStart($toDt($today->modify('-100 days')))
            ->setDateEnd($toDt($today->modify('-85 days')));

        return $out;
    }

    /**
     * 24 lignes : bookId, userKey, rating, createdOffset, texte (≥ 10 caractères).
     *
     * @return list<array{0: int, 1: string, 2: int, 3: string, 4: string}>
     */
    private function reviewsThreePerBook(): array
    {
        return [
            [1, 'user', 5, '-50 days', 'Une fresque humaine bouleversante : Valjean et Cosette m’ont accompagné longtemps après la dernière page. Indispensable.'],
            [1, 'user1', 5, '-48 days', 'J’ai mis du temps à me lancer mais l’ampleur du roman vaut chaque heure passée. Hugo mélange drame et espérance avec maestria.'],
            [1, 'user2', 4, '-46 days', 'Certaines digressions historiques m’ont semblé longues, mais l’ensemble reste un monument qu’on est fier d’avoir lu.'],

            [2, 'user3', 4, '-72 days', 'Paris médiéval magnifiquement évoqué ; Esmeralda et Quasimodo sont des figures qui ne s’effacent pas. Très beau roman.'],
            [2, 'user4', 5, '-70 days', 'Une fresque sombre et romanesque : la cathédrale devient presque un personnage. J’ai adoré le rythme et les images.'],
            [2, 'user5', 4, '-68 days', 'Plus mélodramatique que Les Misérables à mon goût, mais l’écriture de Hugo transporte toujours autant.'],

            [3, 'user6', 4, '-55 days', 'Georges Duroy est insupportable au bon sens : Maupassant décrit la presse et l’ambition sans fard. Lecture prenante.'],
            [3, 'user7', 3, '-53 days', 'J’ai trouvé le héros trop cynique pour m’attacher, mais le portrait du Paris mondain est saisissant de vérité.'],
            [3, 'user8', 5, '-51 days', 'Roman court, nerveux, sans concession. On ne peut pas lâcher avant la fin tant Duroy fascine et répugne à la fois.'],

            [4, 'user9', 5, '-44 days', 'Molière au sommet : chaque réplique de Tartuffe fait alterner rire et gêne. À lire ou à voir au théâtre absolument.'],
            [4, 'user10', 5, '-42 days', 'Comédie intemporelle sur l’hypocrisie ; ma classe de français m’avait déjà convaincu, je confirme en adulte.'],
            [4, 'user11', 4, '-40 days', 'Langue un peu archaïque mais les enjeux restent d’une modernité troublante. Excellent pour découvrir le théâtre classique.'],

            [5, 'user12', 4, '-38 days', 'Racine au plus serré : les passions politiques et familiales explosent dans un huis clos royal. Très intense pour une pièce courte.'],
            [5, 'user13', 5, '-36 days', 'Néron naît sous nos yeux ; la jalousie d’Agrippine est terrifiante. Une tragédie limpide et implacable.'],
            [5, 'user14', 4, '-34 days', 'J’ai dû relire certaines scènes pour tout suivre, mais la tension dramatique ne faiblit jamais jusqu’au dénouement.'],

            [6, 'user15', 5, '-62 days', '1984 reste un avertissement : surveillance, langage, pouvoir… Des thèmes toujours d’actualité. Lecture essentielle.'],
            [6, 'user16', 5, '-60 days', 'Orwell écrit avec une froideur qui rend l’angoisse encore plus forte. La fin m’a laissé sans voix pendant des jours.'],
            [6, 'user17', 4, '-58 days', 'Roman court mais dense ; certains passages didactiques ralentissent un peu l’action, sans nuire à la force globale.'],

            [7, 'user', 5, '-30 days', 'Elizabeth et Darcy sont devenus mes repères du roman de mœurs : ironie, tension romantique et intelligence partout.'],
            [7, 'user3', 4, '-28 days', 'Un classique rafraîchissant : les dialogues pétillants compensent largement le cadre très « salon » au début.'],
            [7, 'user6', 5, '-26 days', 'Jane Austen décrit les contraintes sociales avec finesse ; la romance est subtile et jamais mièvre. Un vrai coup de cœur.'],

            [8, 'user9', 5, '-22 days', 'Poèmes d’une tristesse magnifique après la perte de Léopoldine ; Hugo touche au plus près du deuil et de la foi.'],
            [8, 'user12', 4, '-20 days', 'Recueil court mais chaque vers compte ; à lire lentement, à voix basse, pour saisir toute la musicalité.'],
            [8, 'user15', 5, '-18 days', 'Parmi les plus beaux textes de Hugo : la douleur devient presque lumineuse. Parfait pour qui aime la poésie du XIXe siècle.'],
        ];
    }
}
