<?php

declare(strict_types=1);

namespace App\Agent\MessageHandler;

use App\Agent\Message\RunNextActor;
use App\Agent\ModelChoiceFactory;
use App\Agent\Werewolf\NextActorRunner;
use App\Agent\Werewolf\StepOutcome;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Runs one step for the subject and, while the game goes on, queues the next one. Locked, paused, finished and
 * missing subjects stop the chain; a resumed human input queues a new RunNextActor (RunNextActorOnResolved).
 * Only Werewolf has agents so far; other workflows are logged and dropped.
 */
#[AsMessageHandler]
final readonly class RunNextActorHandler
{
    public function __construct(
        private NextActorRunner $werewolf,
        private ModelChoiceFactory $models,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(RunNextActor $message): void
    {
        if (NextActorRunner::WORKFLOW !== $message->workflow) {
            $this->logger->notice('RunNextActor: no agents for workflow {workflow}; subject {subject} left alone.', ['workflow' => $message->workflow, 'subject' => $message->subjectId]);

            return;
        }

        $step = $this->werewolf->step($message->subjectId, $this->models->create());
        $this->logger->info('RunNextActor {subject}: {outcome}, {turns} turn(s){expired}.', [
            'subject' => $message->subjectId,
            'outcome' => $step->outcome->value,
            'turns' => \count($step->turns),
            'expired' => $step->expired ? ', deadline applied' : '',
        ]);
        if (StepOutcome::Played === $step->outcome && !$step->gameOver) {
            $this->bus->dispatch(new RunNextActor($message->workflow, $message->subjectId));
        }
    }
}
