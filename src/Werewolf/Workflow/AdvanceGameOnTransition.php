<?php

declare(strict_types=1);

namespace App\Werewolf\Workflow;

use App\Werewolf\Entity\WerewolfGame;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/** Applying a moderator transition resolves the phase in the rules engine; PhaseGuard made sure the places agree. */
#[AsEventListener('workflow.werewolf_game.transition')]
final readonly class AdvanceGameOnTransition
{
    /** @param TransitionEvent<object> $event */
    public function __invoke(TransitionEvent $event): void
    {
        $subject = $event->getSubject();
        if ($subject instanceof WerewolfGame) {
            $subject->game()->advance();
        }
    }
}
