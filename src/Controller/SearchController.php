<?php

namespace App\Controller;

use App\Repository\WorkRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    #[Route('/search/works', name: 'app_search_works', methods: ['GET'])]
    public function works(Request $request, WorkRepository $workRepository): JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        if ($q === '' || mb_strlen($q) < 2) {
            return $this->json(['items' => []]);
        }

        $items = $workRepository->searchForNavbar($q, 8);

        return $this->json(['items' => $items]);
    }
}
