<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Comment;
use App\Entity\Favorite;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\BookCommentType;
use App\Repository\BookRepository;
use App\Repository\CategoryRepository;
use App\Repository\CommentRepository;
use App\Repository\FavoriteRepository;
use App\Repository\LanguageRepository;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use App\Service\BookBorrowEligibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/book')]
final class BookController extends AbstractController
{
    #[Route('/catalogue', name: 'app_book_catalogue')]
    public function catalogue(
        Request $request,
        BookRepository $bookRepository,
        CategoryRepository $categoryRepository,
        LanguageRepository $languageRepository,
    ): Response {
        $all = $request->query->all();
        $categoryIds = $this->parseIdsFromQuery($all['cat'] ?? null);
        $languageIds = $this->parseIdsFromQuery($all['lang'] ?? null);

        $q = $request->query->getString('q', '');
        $genreQ = $request->query->getString('genre_q', '');
        $sort = $request->query->getString('sort', 'title');
        if (!\in_array($sort, ['title', 'title_desc', 'stock', 'random'], true)) {
            $sort = 'title';
        }

        $inStockOnly = $request->query->getBoolean('in_stock');

        $books = $bookRepository->findForCatalogue($q, $categoryIds, $languageIds, $genreQ, $sort, $inStockOnly);
        if ($sort === 'random') {
            shuffle($books);
        }

        return $this->render('book/catalogue.html.twig', [
            'books' => $books,
            'categories' => $categoryRepository->findBy([], ['name' => 'ASC']),
            'languages' => $languageRepository->findBy([], ['country' => 'ASC']),
            'filters' => [
                'q' => $q,
                'genre_q' => $genreQ,
                'sort' => $sort,
                'cat' => $categoryIds,
                'lang' => $languageIds,
                'in_stock' => $inStockOnly,
            ],
        ]);
    }

    #[Route('/favoris', name: 'app_book_favorites')]
    #[IsGranted('ROLE_USER')]
    public function favorites(BookRepository $bookRepository): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $this->render('book/favorites.html.twig', [
            'books' => $bookRepository->findFavoriteBooksForUser($user),
        ]);
    }

    #[Route('/{id}/favori', name: 'app_book_favorite', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function addFavorite(
        Request $request,
        Book $book,
        EntityManagerInterface $em,
        FavoriteRepository $favoriteRepository,
    ): Response {

        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('danger', 'Session expirée ou formulaire invalide. Réessayez.');

            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
        }

        $existing = $favoriteRepository->findOneBy(['user' => $user, 'book' => $book]);
        if ($existing !== null) {
            $em->remove($existing);
            $em->flush();
            $this->addFlash('success', 'Livre retiré de vos favoris.');
        } else {
            $favorite = (new Favorite())->setUser($user)->setBook($book);
            $em->persist($favorite);
            $em->flush();
            $this->addFlash('success', 'Livre ajouté à vos favoris.');
        }

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }

    #[Route('/{id}/emprunter', name: 'app_book_borrow', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function borrow(
        Request $request,
        Book $book,
        EntityManagerInterface $em,
        ReservationRepository $reservationRepository,
        BookBorrowEligibility $borrowEligibility,
    ): Response {

        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('danger', 'Session expirée ou formulaire invalide. Réessayez.');

            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
        }

        // Vérifier si le livre est disponible à l'emprunt
        if (!$borrowEligibility->isBookBorrowableInPrinciple($book)) {
            $this->addFlash('warning', 'Ce livre n’est pas disponible à l’emprunt pour le moment.');

            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
        }

        // Récupérer la date la plus proche pour l'emprunt
        $earliest = $borrowEligibility->earliestAllowedStartDate($book);
        if (null === $earliest) {
            $this->addFlash('warning', 'Ce livre n’est pas disponible à l’emprunt pour le moment.');

            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
        }

        // Vérifier si l'utilisateur a déjà une demande ou un prêt en cours pour ce livre
        if ($reservationRepository->findOpenReservationForUserAndBook($user, $book) !== null) {
            $this->addFlash('info', 'Vous avez déjà réaliser une demande d\'emprunt pour ce livre.');

            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
        }

        // Créer une nouvelle demande d'emprunt
        $reservation = new Reservation();
        $form = $this->createForm(ReservationType::class, $reservation, [
            'action' => $this->generateUrl('app_book_borrow', ['id' => $book->getId()]),
            'method' => 'POST',
            'min_date_start' => \DateTime::createFromInterface($earliest),
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            $this->addFlash('danger', 'Requête invalide.');

            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
        }
        if (!$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('warning', $error->getMessage());
            }

            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
        }

        $start = $reservation->getDateStart();
        $end = $reservation->getDateEnd();
        if (!$start instanceof \DateTimeInterface || !$end instanceof \DateTimeInterface) {
            $this->addFlash('danger', 'Dates invalides.');

            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
        }

        $periodError = $borrowEligibility->validateRequestedPeriod($book, $start, $end);
        if (null !== $periodError) {
            $this->addFlash('warning', $periodError);

            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
        }

        \assert($user instanceof User);
        $reservation->setBook($book)->setUser($user)->setStatus(0);

        $em->persist($reservation);
        $em->flush();

        $this->addFlash('success', 'Votre demande d’emprunt a été enregistrée.');

        return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
    }

    #[Route('/{id}/comment/{commentId}/disable', name: 'app_book_comment_disable', methods: ['POST'], requirements: ['id' => '\\d+', 'commentId' => '\\d+'])]
    #[IsGranted('ROLE_LIBRARIAN')]
    public function disableComment(
        Request $request,
        Book $book,
        int $commentId,
        CommentRepository $commentRepository,
        EntityManagerInterface $em,
    ): Response {
        $comment = $commentRepository->find($commentId);
        if (null === $comment || $comment->getBook() !== $book) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('comment_disable_'.$comment->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$comment->isActive()) {
            $this->addFlash('info', 'Cet avis était déjà masqué.');

            return $this->redirectToBookShowWithCommentsAnchor($book->getId(), $request->request->getString('comments_sort'));
        }

        $comment->setIsActive(false);
        $em->flush();

        $this->addFlash('success', 'L’avis a été masqué. L’usager ne pourra plus commenter cet livre.');

        return $this->redirectToBookShowWithCommentsAnchor($book->getId(), $request->request->getString('comments_sort'));
    }

    #[Route('/{id}/comment/{commentId}/enable', name: 'app_book_comment_enable', methods: ['POST'], requirements: ['id' => '\\d+', 'commentId' => '\\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function enableComment(
        Request $request,
        Book $book,
        int $commentId,
        CommentRepository $commentRepository,
        EntityManagerInterface $em,
    ): Response {
        $comment = $commentRepository->find($commentId);
        if (null === $comment || $comment->getBook() !== $book) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('comment_enable_'.$comment->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if ($comment->isActive()) {
            $this->addFlash('info', 'Cet avis était déjà visible.');

            return $this->redirectToBookShowWithCommentsAnchor($book->getId(), $request->request->getString('comments_sort'));
        }

        $comment->setIsActive(true);
        $em->flush();

        $this->addFlash('success', 'L’avis a été réactivé et reparaît dans la liste publique. L’usager peut à nouveau modifier son avis s’il a le droit de commenter.');

        return $this->redirectToBookShowWithCommentsAnchor($book->getId(), $request->request->getString('comments_sort'));
    }

    #[Route('/{id}/comment', name: 'app_book_comment', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function postComment(
        Request $request,
        Book $book,
        EntityManagerInterface $em,
        ReservationRepository $reservationRepository,
        CommentRepository $commentRepository,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_auth_login');
        }

        if (!$reservationRepository->userCanCommentOnBook($user, $book)) {
            $this->addFlash('warning', 'Vous ne pouvez publier un avis qu’après avoir rendu ce livre à la bibliothèque.');

            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
        }

        $existing = $commentRepository->findOneByUserAndBook($user, $book);
        if (null !== $existing && !$existing->isActive()) {
            $this->addFlash('warning', 'Votre avis sur cet livre a été retiré par la modération. Vous ne pouvez plus publier de commentaire sur ce livre.');

            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
        }

        $comment = $existing ?? (new Comment())->setUser($user)->setBook($book)->setIsActive(true);

        $form = $this->createForm(BookCommentType::class, $comment, [
            'csrf_token_id' => 'book_comment_'.$book->getId(),
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            $this->addFlash('danger', 'Requête invalide.');

            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
        }
        if (!$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('warning', $error->getMessage());
            }

            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
        }

        if (null === $existing) {
            $em->persist($comment);
        }
        $em->flush();

        $this->addFlash('success', null === $existing ? 'Votre avis a été publié.' : 'Votre avis a été mis à jour.');

        $sort = $request->request->getString('comments_sort');
        $params = ['id' => $book->getId()];
        if (\in_array($sort, ['recent', 'oldest', 'rating_high', 'rating_low'], true)) {
            $params['comments_sort'] = $sort;
        }

        return $this->redirectToRoute('app_book_show', $params);
    }

    #[Route('/{id}', name: 'app_book_show', requirements: ['id' => '\\d+'])]
    public function show(
        Request $request,
        Book $book,
        FavoriteRepository $favoriteRepository,
        BookBorrowEligibility $borrowEligibility,
        CommentRepository $commentRepository,
        ReservationRepository $reservationRepository,
    ): Response {
        $user = $this->getUser();
        $isFavorite = $user instanceof User
            && $favoriteRepository->findOneBy(['user' => $user, 'book' => $book]) !== null;

        $borrowAllowed = $borrowEligibility->isBookBorrowableInPrinciple($book);
        $earliest = $borrowEligibility->earliestAllowedStartDate($book);
        $minStartForForm = null !== $earliest ? \DateTime::createFromInterface($earliest) : new \DateTime('today');
        $defaultStart = \DateTime::createFromInterface($minStartForForm);
        $borrowDraft = (new Reservation())
            ->setDateStart($defaultStart)
            ->setDateEnd((clone $defaultStart)->modify('+7 days'));

        $borrowFormView = $this->createForm(ReservationType::class, $borrowDraft, [
            'action' => $this->generateUrl('app_book_borrow', ['id' => $book->getId()]),
            'method' => 'POST',
            'min_date_start' => $minStartForForm,
        ])->createView();

        $commentSort = $request->query->getString('comments_sort', 'recent');
        if (!\in_array($commentSort, ['recent', 'oldest', 'rating_high', 'rating_low'], true)) {
            $commentSort = 'recent';
        }

        $comments = $commentRepository->findPublishedForBookOrdered($book, $commentSort);
        $commentStats = $commentRepository->getPublishedStatsForBook($book);
        $hiddenComments = $this->isGranted('ROLE_ADMIN')
            ? $commentRepository->findHiddenForBookOrdered($book, $commentSort)
            : [];

        $commentFormView = null;
        $canCommentBook = false;
        $commentBlockedByModeration = false;
        if ($user instanceof User) {
            $ownComment = $commentRepository->findOneByUserAndBook($user, $book);
            $commentBlockedByModeration = null !== $ownComment && !$ownComment->isActive();
            $canCommentBook = $reservationRepository->userCanCommentOnBook($user, $book) && !$commentBlockedByModeration;
            if ($canCommentBook) {
                $commentEntity = $ownComment ?? (new Comment())->setUser($user)->setBook($book)->setIsActive(true);
                $commentFormView = $this->createForm(BookCommentType::class, $commentEntity, [
                    'action' => $this->generateUrl('app_book_comment', ['id' => $book->getId()]),
                    'method' => 'POST',
                    'csrf_token_id' => 'book_comment_'.$book->getId(),
                ])->createView();
            }
        }

        return $this->render('book/show.html.twig', [
            'book' => $book,
            'is_favorite' => $isFavorite,
            'borrow_form' => $borrowFormView,
            'borrow_allowed' => $borrowAllowed,
            'borrow_earliest' => $earliest,
            'comments' => $comments,
            'comments_sort' => $commentSort,
            'comment_stats' => $commentStats,
            'comment_form' => $commentFormView,
            'can_comment_book' => $canCommentBook,
            'comment_blocked_by_moderation' => $commentBlockedByModeration,
            'hidden_comments' => $hiddenComments,
        ]);
    }

    private function redirectToBookShowWithCommentsAnchor(int $bookId, string $commentsSort): Response
    {
        $params = ['id' => $bookId];
        if (\in_array($commentsSort, ['recent', 'oldest', 'rating_high', 'rating_low'], true)) {
            $params['comments_sort'] = $commentsSort;
        }

        return $this->redirect($this->generateUrl('app_book_show', $params) . '#avis-lecteurs');
    }

    /**
     * Accepte une liste d’IDs séparés par des virgules dans l’URL (`cat=1,2`), un seul ID, ou encore
     * l’ancien format tableau (`cat[]=1`) pour compatibilité.
     *
     * @return list<int>
     */
    private function parseIdsFromQuery(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (\is_array($raw)) {
            $ints = array_map('intval', $raw);
        } elseif (\is_string($raw)) {
            $parts = preg_split('/\s*,\s*/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
            $ints = array_map('intval', false === $parts ? [] : $parts);
        } else {
            return [];
        }

        return array_values(array_unique(array_filter($ints, static fn (int $id): bool => $id > 0)));
    }
}
