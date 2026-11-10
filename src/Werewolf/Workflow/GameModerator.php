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
