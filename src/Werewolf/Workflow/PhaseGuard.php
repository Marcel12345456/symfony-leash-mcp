<?php

declare(strict_types=1);

namespace App\Werewolf\Workflow;

use App\Werewolf\Entity\WerewolfGame;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * A moderator transition is enabled only when the phase waits for nobody (or its deadline passed) and the rules engine would land in
 * exactly the transition's target place (checked on a clone: Game holds only arrays and immutable values).
 */
#[AsEventListener('workflow.werewolf_game.guard')]
final readonly class PhaseGuard
{
    /** @param GuardEvent<object> $event */
    public function __invoke(GuardEvent $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof WerewolfGame) {
            return;
        }

        $game = $subject->game();
        $pending = $game->pendingSeats();
        if ([] !== $pending && !$subject->deadlinePassed()) {
            $event->setBlocked(true, \sprintf('Waiting for seats %s.', implode(', ', $pending)));

            return;
        }
        if (null !== $game->winner()) {
            $event->setBlocked(true, 'The game is over.');

            return;
        }

        $peek = clone $game;
        $peek->advance();
        if ($peek->phase()->value !== ($event->getTransition()->getTos()[0] ?? null)) {
            $event->setBlocked(true, \sprintf('The game moves to %s next.', $peek->phase()->value));
        }
    }
}
