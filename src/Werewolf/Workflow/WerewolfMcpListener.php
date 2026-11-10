<?php

declare(strict_types=1);

namespace App\Werewolf\Workflow;

use App\Werewolf\Entity\WerewolfGame;
use Doctrine\ORM\EntityManagerInterface;
use Leash\WorkflowMcp\Event\CapabilityDenied;
use Leash\WorkflowMcp\Event\ToolCalled;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** Werewolf reactions to MCP calls: an accepted seat action lets the moderator move on; a denial is logged on the game. */
final readonly class WerewolfMcpListener
{
    public function __construct(
        private GameModerator $moderator,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[AsEventListener]
    public function onToolCalled(ToolCalled $event): void
    {
        if ($event->subject instanceof WerewolfGame && !$event->isError) {
            $this->moderator->advance($event->subject);
        }
    }

    #[AsEventListener]
    public function onCapabilityDenied(CapabilityDenied $event): void
    {
        if (!$event->subject instanceof WerewolfGame) {
            return;
        }

        $event->subject->recordDenial($event->caller->getUserIdentifier(), $event->tool, $event->places);
        $this->entityManager->flush();
        $this->logger->warning('Werewolf: {caller} asked for {capability} in {places}; denied.', [
            'caller' => $event->caller->getUserIdentifier(),
            'capability' => $event->tool,
            'places' => implode(', ', $event->places),
            'game' => $event->subject->getId(),
        ]);
    }
}
