<?php

declare(strict_types=1);

namespace App\Werewolf\Workflow;

use App\Werewolf\Entity\WerewolfGame;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Deterministic moderator (PLAN §3.5): applies moderator transitions while one is enabled, i.e. while the current
 * phase waits for nobody. Seats never hold ROLE_MODERATOR, so they never see these transitions as tools.
 */
final readonly class GameModerator
{
    public function __construct(
        #[Target('werewolf_game')]
        private WorkflowInterface $workflow,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * The current turn's deadline passed (PLAN §3.5): in day_discussion the speaker is skipped; in any other phase
     * the missing actions abstain and the phase resolves. Then the moderator advances as usual.
     *
     * @return list<string> the transitions applied, in order
     */
    public function expire(WerewolfGame $game): array
    {
        $rules = $game->game();
        if (null !== $rules->currentSpeaker()) {
            $rules->skipSpeaker();
            $this->entityManager->flush();

            return $this->advance($game);
        }

        $game->setDeadlinePassed(true);
        try {
            $transition = $this->workflow->getEnabledTransitions($game)[0] ?? null;
            if (null === $transition) {
                return [];
            }
            $this->workflow->apply($game, $transition->getName());
        } finally {
            $game->setDeadlinePassed(false);
        }
        $this->entityManager->flush();

        return [$transition->getName(), ...$this->advance($game)];
    }

    /** @return list<string> the transitions applied, in order */
    public function advance(WerewolfGame $game): array
    {
        $applied = [];
        while ($transition = $this->workflow->getEnabledTransitions($game)[0] ?? null) {
            $this->workflow->apply($game, $transition->getName());
            $applied[] = $transition->getName();
        }
        if ([] !== $applied) {
            $this->entityManager->flush();
        }

        return $applied;
    }
}
