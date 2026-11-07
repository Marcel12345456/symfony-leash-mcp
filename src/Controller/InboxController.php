<?php

declare(strict_types=1);

namespace App\Controller;

use Leash\HumanInput\Inbox\ElicitationForm;
use Leash\HumanInput\Pending\PendingInput;
use Leash\HumanInput\Pending\PendingInputAnswers;
use Leash\HumanInput\Pending\PendingInputs;
use Leash\HumanInput\Pending\PendingInputStatus;
use Mcp\Schema\Enum\ElicitAction;
use Mcp\Schema\Result\ElicitResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Exception\ExpiredSignedUriException;
use Symfony\Component\HttpFoundation\Exception\SignedUriException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/** Web inbox (PLAN §3.3): pending human inputs, each rendered from its elicitation schema. */
final class InboxController extends AbstractController
{
    public function __construct(
        private readonly PendingInputs $pending,
        private readonly PendingInputAnswers $answers,
        private readonly ElicitationForm $forms,
        private readonly UriSigner $signer,
    ) {}

    #[Route('/inbox', name: 'inbox', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('inbox/index.html.twig', ['items' => $this->pending->withStatus(PendingInputStatus::Pending)]);
    }

    /** Logged-in approvers or a valid signed link (expired → 410, unsigned/tampered → 403). */
    #[Route('/inbox/item/{id}', name: 'inbox_item', methods: ['GET', 'POST'])]
    public function item(string $id, Request $request): Response
    {
        if (!$this->isGranted('ROLE_APPROVER')) {
            try {
                $this->signer->verify($request);
            } catch (ExpiredSignedUriException) {
                throw new GoneHttpException('This link has expired; ask for a new one or log in to the inbox.');
            } catch (SignedUriException) {
                throw new AccessDeniedHttpException('This link is not valid; log in to the inbox.');
            }
        }
        $pending = $this->pending->get($id) ?? throw $this->createNotFoundException(\sprintf('No pending input #%s.', $id));
        if (PendingInputStatus::Pending !== $pending->status) {
            return $this->render('inbox/item.html.twig', ['item' => $pending, 'asks' => []]);
        }

        $asks = $this->asks($pending);
        if ($request->isMethod('POST')) {
            if ('decline' === $request->request->getString('_decision')) {
                $this->answers->answer($id, array_map(static fn(): ElicitResult => new ElicitResult(ElicitAction::Decline), $asks));

                return $this->render('inbox/item.html.twig', ['item' => $pending, 'asks' => []]);
            }
            $answers = [];
            foreach ($asks as $key => $ask) {
                $ask['form']->handleRequest($request);
                if ($ask['form']->isSubmitted() && $ask['form']->isValid()) {
                    $answers[$key] = new ElicitResult(ElicitAction::Accept, $this->forms->content($ask['schema'], $ask['form']));
                }
            }
            if (\count($answers) === \count($asks)) {
                $this->answers->answer($id, $answers);

                return $this->render('inbox/item.html.twig', ['item' => $pending, 'asks' => []]);
            }
        }

        return $this->render('inbox/item.html.twig', [
            'item' => $pending,
            'asks' => array_map(static fn(array $ask): array => ['message' => $ask['message'], 'form' => $ask['form']->createView()], $asks),
        ], new Response(status: $request->isMethod('POST') ? 422 : 200));
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $auth): Response
    {
        return $this->render('security/login.html.twig', ['error' => $auth->getLastAuthenticationError(), 'username' => $auth->getLastUsername()]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Handled by the firewall.');
    }

    /** @return array<string, array{message: string, schema: array<mixed>, form: FormInterface<mixed>}> */
    private function asks(PendingInput $pending): array
    {
        $asks = [];
        foreach ($pending->inputRequests as $key => $ask) {
            $params = $ask['params'] ?? null;
            if ('elicitation/create' !== ($ask['method'] ?? null) || !\is_array($params) || !\is_array($params['requestedSchema'] ?? null)) {
                throw new \UnexpectedValueException(\sprintf('Pending input #%s asks for "%s", which the inbox cannot answer.', $pending->id, \is_string($ask['method'] ?? null) ? $ask['method'] : '?'));
            }
            $schema = $params['requestedSchema'];
            $asks[$key] = [
                'message' => \is_string($params['message'] ?? null) ? $params['message'] : '',
                'schema' => $schema,
                'form' => $this->forms->create($schema, $key),
            ];
        }

        return $asks;
    }
}
