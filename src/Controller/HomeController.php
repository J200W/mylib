<?php

namespace App\Controller;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\Category;
use App\Entity\Language;
use App\Entity\Reservation;
use App\Entity\User;
use App\Form\AuthorType;
use App\Form\BookType;
use App\Form\CategoryType;
use App\Form\AdminUserEditType;
use App\Form\LanguageType;
use App\Form\ReservationType;
use App\Service\BookBorrowEligibility;
use App\Service\BookCoverUploader;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use App\Repository\CategoryRepository;
use App\Repository\LanguageRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class HomeController extends AbstractController
{

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    #[Route('/account', name: 'app_account')]
    #[IsGranted('ROLE_USER')]
    public function account(
        BookRepository $bookRepository,
        ReservationRepository $reservationRepository,
        BookBorrowEligibility $borrowEligibility,
    ): Response {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_auth_login');
        }

        $favoritesCount = $bookRepository->countFavoriteBooksForUser($user);
        $favoritePreview = $bookRepository->findFavoriteBooksForUser($user, 8);
        $reservations = $reservationRepository->findForAccountByUser($user);

        $reservationEditForms = [];
        foreach ($reservations as $r) {
            if (($r->getStatus() ?? 0) === 0) {
                $book = $r->getBook();
                $earliest = null !== $book ? $borrowEligibility->earliestAllowedStartDate($book) : null;
                $minStart = null !== $earliest ? \DateTime::createFromInterface($earliest) : new \DateTime('today');
                $reservationEditForms[$r->getId()] = $this->createForm(ReservationType::class, $r, [
                    'action' => $this->generateUrl('app_account_reservation_update', ['id' => $r->getId()]),
                    'method' => 'POST',
                    'min_date_start' => $minStart,
                ])->createView();
            }
        }

        return $this->render('home/account.html.twig', [
            'favorite_preview' => $favoritePreview,
            'favorites_count' => $favoritesCount,
            'has_more_favorites' => $favoritesCount > 8,
            'reservations' => $reservations,
            'reservation_edit_forms' => $reservationEditForms,
        ]);
    }

    #[Route('/admin', name: 'app_admin', methods: ['GET'])]
    #[IsGranted('ROLE_LIBRARIAN')]
    public function admin(
        Request $request,
        BookRepository $bookRepository,
        AuthorRepository $authorRepository,
        CategoryRepository $categoryRepository,
        LanguageRepository $languageRepository,
        ReservationRepository $reservationRepository,
        UserRepository $userRepository,
    ): Response {
        $historyUserId = $request->query->getInt('history_user', 0);

        $filterUser = $historyUserId > 0 ? $userRepository->find($historyUserId) : null;
        $adminReservationHistory = $reservationRepository->findForAdminHistory($filterUser);

        $allUsersForFilter = $userRepository->findAllOrderedByEmail();
        $adminUsersTable = $this->isGranted('ROLE_ADMIN')
            ? $allUsersForFilter
            : [];

        return $this->render('home/admin.html.twig', [
            'admin_books' => $bookRepository->findAll(),
            'admin_authors' => $authorRepository->findAll(),
            'admin_categories' => $categoryRepository->findAll(),
            'admin_languages' => $languageRepository->findAll(),
            'admin_pending_reservations' => $reservationRepository->findPendingForAdmin(),
            'admin_reservation_history' => $adminReservationHistory,
            'admin_users_for_filter' => $allUsersForFilter,
            'admin_history_filter_user_id' => null !== $filterUser ? $filterUser->getId() : null,
            'admin_users_table' => $adminUsersTable,
        ]);
    }

    #[Route('/admin/reservation/{id}/accept', name: 'app_admin_reservation_accept', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_LIBRARIAN')]
    public function adminReservationAccept(
        Request $request,
        Reservation $reservation,
        EntityManagerInterface $em,
    ): Response {
        $id = $reservation->getId();
        if (null === $id) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('admin_reservation_accept_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (($reservation->getStatus() ?? 0) !== 0) {
            $this->addFlash('warning', 'Cette demande n’est plus en attente.');

            return $this->redirect($this->generateUrl('app_admin').'#admin-pending-reservations');
        }

        $book = $reservation->getBook();
        if (null !== $book) {
            $stock = $book->getStock();
            if (null === $stock || $stock < 1) {
                $this->addFlash('danger', 'Stock insuffisant : impossible d’accepter cette demande.');

                return $this->redirect($this->generateUrl('app_admin').'#admin-pending-reservations');
            }
            $book->setStock($stock - 1);
        }

        $reservation->setStatus(1);
        $em->flush();

        $this->addFlash('success', 'La demande d’emprunt a été acceptée.');

        return $this->redirect($this->generateUrl('app_admin').'#admin-pending-reservations');
    }

    #[Route('/admin/reservation/{id}/reject', name: 'app_admin_reservation_reject', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_LIBRARIAN')]
    public function adminReservationReject(
        Request $request,
        Reservation $reservation,
        EntityManagerInterface $em,
    ): Response {
        $id = $reservation->getId();
        if (null === $id) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('admin_reservation_reject_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (($reservation->getStatus() ?? 0) !== 0) {
            $this->addFlash('warning', 'Cette demande n’est plus en attente.');

            return $this->redirect($this->generateUrl('app_admin').'#admin-pending-reservations');
        }

        $reservation->setStatus(2);
        $em->flush();

        $this->addFlash('success', 'La demande d’emprunt a été refusée.');

        return $this->redirect($this->generateUrl('app_admin').'#admin-pending-reservations');
    }

    #[Route('/admin/user/{id}/role', name: 'app_admin_user_role', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminUserRole(
        Request $request,
        User $target,
        EntityManagerInterface $em,
        UserRepository $userRepository,
    ): Response {
        $tid = $target->getId();
        if (null === $tid) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('admin_user_role_'.$tid, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $current = $this->getUser();
        if (!$current instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $role = $request->request->getString('role');
        if (!\in_array($role, ['ROLE_USER', 'ROLE_LIBRARIAN', 'ROLE_ADMIN'], true)) {
            $this->addFlash('danger', 'Rôle invalide.');

            return $this->redirect($this->generateUrl('app_admin').'#admin-users');
        }

        if ($current->getId() === $tid && 'ROLE_ADMIN' !== $role) {
            $this->addFlash('warning', 'Vous ne pouvez pas retirer votre propre rôle d’administrateur depuis cette interface.');

            return $this->redirect($this->generateUrl('app_admin').'#admin-users');
        }

        if ('ROLE_ADMIN' !== $role && \in_array('ROLE_ADMIN', $target->getRoles(), true)) {
            $adminCount = 0;
            foreach ($userRepository->findAll() as $u) {
                if (\in_array('ROLE_ADMIN', $u->getRoles(), true)) {
                    ++$adminCount;
                }
            }
            if ($adminCount <= 1) {
                $this->addFlash('warning', 'Impossible de retirer le dernier administrateur.');

                return $this->redirect($this->generateUrl('app_admin').'#admin-users');
            }
        }

        match ($role) {
            'ROLE_ADMIN' => $target->setRoles(['ROLE_ADMIN']),
            'ROLE_LIBRARIAN' => $target->setRoles(['ROLE_LIBRARIAN']),
            default => $target->setRoles([]),
        };

        $em->flush();

        $this->addFlash('success', 'Le rôle de « '.$target->getFirstname().' '.$target->getLastname().' » a été mis à jour.');

        return $this->redirect($this->generateUrl('app_admin').'#admin-users');
    }

    #[Route('/admin/user/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminUserEdit(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $form = $this->createForm(AdminUserEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $p1 = $form->get('plainPassword')->getData();
            $p2 = $form->get('plainPasswordConfirm')->getData();
            $p1 = \is_string($p1) ? trim($p1) : '';
            $p2 = \is_string($p2) ? trim($p2) : '';

            if ('' !== $p1 || '' !== $p2) {
                if ($p1 !== $p2) {
                    $this->addFlash('warning', 'Les deux mots de passe ne correspondent pas.');
                    $em->refresh($user);

                    return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
                }
                if (\strlen($p1) < 8) {
                    $this->addFlash('warning', 'Le mot de passe doit contenir au moins 8 caractères.');
                    $em->refresh($user);

                    return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
                }
                if (1 !== preg_match('/\p{L}/u', $p1) || 1 !== preg_match('/\p{N}/u', $p1)) {
                    $this->addFlash('warning', 'Le mot de passe doit contenir au moins une lettre et un chiffre.');
                    $em->refresh($user);

                    return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
                }

                $user->setPassword($passwordHasher->hashPassword($user, $p1));
            }

            $em->flush();
            $this->addFlash('success', 'Le profil a été mis à jour.');

            return $this->redirect($this->generateUrl('app_admin').'#admin-users');
        }

        return $this->render('home/admin_user_edit.html.twig', [
            'edit_user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/book/new', name: 'app_admin_book_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_LIBRARIAN')]
    public function adminBookNew(
        Request $request,
        EntityManagerInterface $em,
        BookCoverUploader $bookCoverUploader,
    ): Response {
        $book = new Book();
        $form = $this->createForm(BookType::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($book);
            $em->flush();

            $coverFile = $form->get('coverFile')->getData();
            if ($coverFile instanceof UploadedFile) {
                $bookId = $book->getId();
                if (null === $bookId) {
                    $this->addFlash('danger', 'Erreur interne : identifiant du livre introuvable après enregistrement.');

                    return $this->redirectToRoute('app_admin_book_new');
                }
                try {
                    $bookCoverUploader->upload($coverFile, $bookId);
                } catch (\Throwable $e) {
                    $this->addFlash('warning', 'Le livre a été enregistré, mais la couverture n’a pas pu être enregistrée : '.$e->getMessage());

                    return $this->redirect($this->generateUrl('app_admin').'#admin-catalogue');
                }
            }

            $this->addFlash('success', 'Le livre « '.$book->getTitle().' » a été ajouté au catalogue.');

            return $this->redirect($this->generateUrl('app_admin').'#admin-catalogue');
        }

        return $this->render('admin/book_form_page.html.twig', [
            'page_title' => 'Nouvel livre',
            'form' => $form,
        ]);
    }

    #[Route('/admin/author/new', name: 'app_admin_author_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_LIBRARIAN')]
    public function adminAuthorNew(Request $request, EntityManagerInterface $em): Response
    {
        $author = new Author();
        $form = $this->createForm(AuthorType::class, $author);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($author);
            $em->flush();
            $this->addFlash('success', 'L’auteur « '.$author->getFirstname().' '.$author->getLastname().' » a été ajouté.');

            return $this->redirect($this->generateUrl('app_admin').'#admin-catalogue');
        }

        return $this->render('admin/author_form_page.html.twig', [
            'page_title' => 'Nouvel auteur',
            'form' => $form,
        ]);
    }

    #[Route('/admin/category/new', name: 'app_admin_category_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_LIBRARIAN')]
    public function adminCategoryNew(Request $request, EntityManagerInterface $em): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category, ['admin_simple' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($category);
            $em->flush();
            $this->addFlash('success', 'La catégorie « '.$category->getName().' » a été ajoutée.');

            return $this->redirect($this->generateUrl('app_admin').'#admin-catalogue');
        }

        return $this->render('admin/category_form_page.html.twig', [
            'page_title' => 'Nouvelle catégorie',
            'form' => $form,
        ]);
    }

    #[Route('/admin/language/new', name: 'app_admin_language_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_LIBRARIAN')]
    public function adminLanguageNew(Request $request, EntityManagerInterface $em): Response
    {
        $language = new Language();
        $form = $this->createForm(LanguageType::class, $language);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($language);
            $em->flush();
            $this->addFlash('success', 'La langue « '.$language->getCountry().' » a été ajoutée.');

            return $this->redirect($this->generateUrl('app_admin').'#admin-catalogue');
        }

        return $this->render('admin/language_form_page.html.twig', [
            'page_title' => 'Nouvelle langue',
            'form' => $form,
        ]);
    }

    #[Route('/admin/book/{id}/edit', name: 'app_admin_book_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_LIBRARIAN')]
    public function adminBookEdit(
        Request $request,
        Book $book,
        EntityManagerInterface $em,
        BookCoverUploader $bookCoverUploader,
    ): Response {
        $form = $this->createForm(BookType::class, $book, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $coverFile = $form->get('coverFile')->getData();
            if ($coverFile instanceof UploadedFile) {
                $bookId = $book->getId();
                if (null !== $bookId) {
                    try {
                        $bookCoverUploader->upload($coverFile, $bookId);
                    } catch (\Throwable $e) {
                        $this->addFlash('warning', 'Les modifications ont été enregistrées, mais la couverture n’a pas pu être mise à jour : '.$e->getMessage());

                        return $this->redirectToRoute('app_admin_book_edit', ['id' => $bookId]);
                    }
                }
            }

            $this->addFlash('success', 'Le livre « '.$book->getTitle().' » a été mis à jour.');

            return $this->redirect($this->generateUrl('app_admin').'#admin-catalogue');
        }

        return $this->render('admin/book_form_page.html.twig', [
            'page_title' => 'Modifier « '.$book->getTitle().' »',
            'form' => $form,
            'book' => $book,
        ]);
    }

    #[Route('/admin/book/{id}/delete', name: 'app_admin_book_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminBookDelete(
        Request $request,
        Book $book,
        EntityManagerInterface $em,
        BookCoverUploader $bookCoverUploader,
    ): Response {
        $id = $book->getId();
        if (null === $id) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('delete_book_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $title = $book->getTitle() ?? '';
        $bookCoverUploader->deleteIfExists($id);
        $em->remove($book);
        $em->flush();

        $this->addFlash('success', 'Le livre « '.$title.' », ses commentaires, réservations et favoris associés ont été supprimés.');

        return $this->redirect($this->generateUrl('app_admin').'#admin-catalogue');
    }

    #[Route('/admin/author/{id}/edit', name: 'app_admin_author_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminAuthorEdit(Request $request, Author $author, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(AuthorType::class, $author, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'L’auteur « '.$author->getFirstname().' '.$author->getLastname().' » a été mis à jour.');

            return $this->redirect($this->generateUrl('app_admin').'#admin-catalogue');
        }

        return $this->render('admin/author_form_page.html.twig', [
            'page_title' => 'Modifier « '.$author->getFirstname().' '.$author->getLastname().' »',
            'form' => $form,
        ]);
    }

    #[Route('/admin/author/{id}/delete', name: 'app_admin_author_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminAuthorDelete(
        Request $request,
        Author $author,
        EntityManagerInterface $em,
        BookCoverUploader $bookCoverUploader,
    ): Response {
        $id = $author->getId();
        if (null === $id) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('delete_author_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $label = trim(($author->getFirstname() ?? '').' '.($author->getLastname() ?? ''));
        foreach ($author->getBooks() as $book) {
            $bid = $book->getId();
            if (null !== $bid) {
                $bookCoverUploader->deleteIfExists($bid);
            }
        }
        $em->remove($author);
        $em->flush();

        $this->addFlash('success', 'L’auteur « '.$label.' » et tous ses livres associés (commentaires, réservations, favoris) ont été supprimés.');

        return $this->redirect($this->generateUrl('app_admin').'#admin-catalogue');
    }

    #[Route('/admin/category/{id}/edit', name: 'app_admin_category_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminCategoryEdit(Request $request, Category $category, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CategoryType::class, $category, [
            'admin_simple' => true,
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'La catégorie « '.$category->getName().' » a été mise à jour.');

            return $this->redirect($this->generateUrl('app_admin').'#admin-catalogue');
        }

        return $this->render('admin/category_form_page.html.twig', [
            'page_title' => 'Modifier « '.$category->getName().' »',
            'form' => $form,
        ]);
    }

    #[Route('/admin/category/{id}/delete', name: 'app_admin_category_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminCategoryDelete(Request $request, Category $category, EntityManagerInterface $em): Response
    {
        $id = $category->getId();
        if (null === $id) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('delete_category_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $name = $category->getName() ?? '';
        $em->remove($category);
        $em->flush();

        $this->addFlash('success', 'La catégorie « '.$name.' » a été supprimée (les livres restent dans le catalogue, sans cette étiquette).');

        return $this->redirect($this->generateUrl('app_admin').'#admin-catalogue');
    }

    #[Route('/admin/language/{id}/edit', name: 'app_admin_language_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminLanguageEdit(Request $request, Language $language, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(LanguageType::class, $language, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'La langue « '.$language->getCountry().' » a été mise à jour.');

            return $this->redirect($this->generateUrl('app_admin').'#admin-catalogue');
        }

        return $this->render('admin/language_form_page.html.twig', [
            'page_title' => 'Modifier « '.$language->getCountry().' »',
            'form' => $form,
        ]);
    }

    #[Route('/admin/language/{id}/delete', name: 'app_admin_language_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminLanguageDelete(
        Request $request,
        Language $language,
        EntityManagerInterface $em,
        BookCoverUploader $bookCoverUploader,
    ): Response {
        $id = $language->getId();
        if (null === $id) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('delete_language_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $label = $language->getCountry() ?? '';
        foreach ($language->getBooks() as $book) {
            $bid = $book->getId();
            if (null !== $bid) {
                $bookCoverUploader->deleteIfExists($bid);
            }
        }
        $em->remove($language);
        $em->flush();

        $this->addFlash('success', 'La langue « '.$label.' » et tous les livres qui y étaient rattachés ont été supprimés (y compris commentaires, réservations et favoris).');

        return $this->redirect($this->generateUrl('app_admin').'#admin-catalogue');
    }

    #[Route('/account/reservation/{id}/cancel', name: 'app_account_reservation_cancel', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function cancelPendingReservation(
        Request $request,
        Reservation $reservation,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_auth_login');
        }

        $id = $reservation->getId();
        if (null === $id) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('account_reservation_cancel_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if ($reservation->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        $st = $reservation->getStatus();
        if (null !== $st && 0 !== $st) {
            $this->addFlash('warning', 'Seules les demandes en attente peuvent être annulées.');

            return $this->redirectToRoute('app_account');
        }

        $em->remove($reservation);
        $em->flush();

        $this->addFlash('success', 'Votre demande d’emprunt a été annulée.');

        return $this->redirectToRoute('app_account');
    }

    #[Route('/account/reservation/{id}/return', name: 'app_account_reservation_return', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function returnBorrowedBook(
        Request $request,
        Reservation $reservation,
        EntityManagerInterface $em,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_auth_login');
        }

        $id = $reservation->getId();
        if (null === $id) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('account_reservation_return_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if ($reservation->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (1 !== ($reservation->getStatus() ?? 0)) {
            $this->addFlash('warning', 'Seuls les emprunts acceptés en cours peuvent être rendus depuis cette page.');

            return $this->redirectToRoute('app_account');
        }

        $start = $reservation->getDateStart();
        if (!$start instanceof \DateTimeInterface) {
            $this->addFlash('danger', 'Données de réservation invalides.');

            return $this->redirectToRoute('app_account');
        }

        $today = new \DateTimeImmutable('today');
        $startDay = \DateTimeImmutable::createFromInterface($start)->setTime(0, 0, 0);
        if ($today < $startDay) {
            $this->addFlash('info', 'Votre période d’emprunt n’a pas encore commencé. Vous pouvez annuler la demande tant qu’elle est en attente, ou contacter la bibliothèque.');

            return $this->redirectToRoute('app_account');
        }

        $book = $reservation->getBook();
        if (null !== $book) {
            $stock = $book->getStock();
            if (null !== $stock) {
                $book->setStock($stock + 1);
            }
        }

        $reservation->setDateEnd(\DateTime::createFromImmutable($today));
        $reservation->setStatus(3);

        $em->flush();

        $this->addFlash('success', 'Merci, votre retour a été enregistré. L’exemplaire est à nouveau disponible au catalogue.');

        return $this->redirectToRoute('app_account');
    }

    #[Route('/account/reservation/{id}', name: 'app_account_reservation_update', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[IsGranted('ROLE_USER')]
    public function updateReservation(
        Request $request,
        Reservation $reservation,
        EntityManagerInterface $em,
        BookBorrowEligibility $borrowEligibility,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_auth_login');
        }

        if ($reservation->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (($reservation->getStatus() ?? 0) !== 0) {
            $this->addFlash('warning', 'Seules les demandes en attente peuvent être modifiées.');

            return $this->redirectToRoute('app_account');
        }

        $book = $reservation->getBook();
        $earliest = null !== $book ? $borrowEligibility->earliestAllowedStartDate($book) : null;
        $minStart = null !== $earliest ? \DateTime::createFromInterface($earliest) : new \DateTime('today');

        $form = $this->createForm(ReservationType::class, $reservation, [
            'action' => $this->generateUrl('app_account_reservation_update', ['id' => $reservation->getId()]),
            'method' => 'POST',
            'min_date_start' => $minStart,
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            $this->addFlash('danger', 'Requête invalide.');

            return $this->redirectToRoute('app_account');
        }
        if (!$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('warning', $error->getMessage());
            }

            return $this->redirectToRoute('app_account');
        }

        if (null !== $book) {
            $start = $reservation->getDateStart();
            $end = $reservation->getDateEnd();
            if ($start instanceof \DateTimeInterface && $end instanceof \DateTimeInterface) {
                $periodError = $borrowEligibility->validateRequestedPeriod($book, $start, $end);
                if (null !== $periodError) {
                    $this->addFlash('warning', $periodError);

                    return $this->redirectToRoute('app_account');
                }
            }
        }

        $em->flush();

        $this->addFlash('success', 'Vos dates d’emprunt ont été mises à jour.');

        return $this->redirectToRoute('app_account');
    }
}
