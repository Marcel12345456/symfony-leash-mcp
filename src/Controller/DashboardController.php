<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dashboard\DocReviewBoard;
use App\Dashboard\WerewolfSpectator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Dashboard (PLAN §3.7): Werewolf spectator (default tab) and Doc Review. The page polls the JSON routes every second.
 * Public on purpose: it is the local demo screen; roles stay hidden until the viewer asks for `reveal=1`.
 */
final class DashboardController extends AbstractController
{
    public const string DEFAULT_GAME = 'seed-8';

    public function __construct(
        private readonly WerewolfSpectator $spectator,
        private readonly DocReviewBoard $docReview,
    ) {}

    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $games = $this->spectator->gameIds();
        $game = $request->query->getString('game');
        if ('' === $game) {
            $game = \in_array(self::DEFAULT_GAME, $games, true) ? self::DEFAULT_GAME : ($games[0] ?? self::DEFAULT_GAME);
        }

        return $this->render('dashboard/index.html.twig', ['games' => $games, 'game' => $game]);
    }

    #[Route('/dashboard/werewolf/{id}', name: 'dashboard_werewolf', methods: ['GET'])]
    public function werewolf(string $id, Request $request): JsonResponse
    {
        $view = $this->spectator->view($id, $request->query->getBoolean('reveal'));

        return null === $view ? new JsonResponse(['error' => \sprintf('No Werewolf game "%s".', $id)], Response::HTTP_NOT_FOUND) : new JsonResponse($view);
    }

    #[Route('/dashboard/doc-review', name: 'dashboard_doc_review', methods: ['GET'])]
    public function docReview(): JsonResponse
    {
        return new JsonResponse(['documents' => $this->docReview->view()]);
    }
}
