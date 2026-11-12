<?php

declare(strict_types=1);

namespace App\Tests\Integration\Werewolf;

use App\Agent\Message\RunNextActor;
use App\Agent\ModelChoice;
use App\Agent\Werewolf\NextActorRunner;
use App\Agent\Werewolf\StepOutcome;
use App\Tests\Integration\Support\ScriptedSeatPlatform;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\StatelessClient;
use App\Tests\Unit\Werewolf\Rules\Table;
use App\Werewolf\Rules\Phase;
use Leash\HumanInput\Pending\PendingInputAnswers;
use Leash\HumanInput\Pending\PendingInputs;
use Leash\HumanInput\Pending\PendingInputStatus;
use Leash\HumanInput\Pending\ResumePendingInput;
use Leash\HumanInput\Pending\ResumePendingInputHandler;
use Leash\WorkflowMcp\Tool\ElicitMissingInputPolicy;
use Mcp\Schema\Enum\ElicitAction;
use Mcp\Schema\Result\ElicitResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * M6 human seat (PLAN §3.5, decisions/035): seat 3 (Cleo, the seer) is played by a human. Its vote turn asks for
 * the target through `input_required` (pick list of the other living seats), the game pauses, the inbox answer
 * resumes the call, and the next step finishes the vote. Wolves 1, 2; night 1 killed seat 4.
 */
final class HumanSeatTest extends WerewolfMcpCase
{
    private const int HUMAN = Table::CLEO;

    protected function setUp(): void
    {
        parent::setUp();
        $game = Table::game();
        Table::night($game, [Table::ADA => Table::DAN, Table::BEN => Table::DAN]);
        Table::discuss($game);
        $this->createGame('g1', $game);
        $entity = $this->game('g1');
        $entity->setHumanSeat(self::HUMAN);
        $this->entityManager()->flush();
    }

    public function testHumanVoteIsAskedAnsweredAndTheGameContinues(): void
    {
        $model = new ScriptedSeatPlatform([
            1 => [self::vote(5), 'Voted.'],
            2 => [self::vote(5), 'Voted.'],
            5 => [self::vote(1), 'Voted.'],
            6 => [self::vote(1), 'Voted.'],
        ]);

        $paused = $this->runner()->step('g1', new ModelChoice($model->platform(), 'qwen3:8b'));

        self::assertSame(StepOutcome::Paused, $paused->outcome);
        self::assertFalse($paused->expired, 'the human seat is waited for, not abstained');
        self::assertSame([1, 1, 2, 2], $model->asked, 'the human seat never asks the model');
        self::assertCount(3, $paused->turns);
        $game = $this->game('g1')->game();
        self::assertSame(Phase::DayVote, $game->phase());
        self::assertContains(self::HUMAN, $game->pendingSeats(), 'nothing recorded before the answer');

        [$pending] = $this->service(PendingInputs::class)->withStatus(PendingInputStatus::Pending);
        self::assertSame('werewolf_game:g1', $pending->subjectRef);
        self::assertSame('seat:3', $pending->callerId);
        self::assertSame('ww_vote', $pending->tool);
        $schema = StatelessClient::at($pending->inputRequests, ElicitMissingInputPolicy::KEY, 'params', 'requestedSchema');
        self::assertSame(['target'], StatelessClient::at($schema, 'required'), 'exactly the missing field; the optional reason is not asked');
        $options = StatelessClient::at($schema, 'properties', 'target', 'oneOf');
        self::assertIsArray($options);
        self::assertSame(['1', '2', '5', '6'], array_column($options, 'const'), 'the other living seats');

        self::assertSame(StepOutcome::Paused, $this->runner()->step('g1', new ModelChoice($model->platform(), 'qwen3:8b'))->outcome);

        $this->service(PendingInputAnswers::class)->answer($pending->id, [ElicitMissingInputPolicy::KEY => new ElicitResult(ElicitAction::Accept, ['target' => '1'])]);
        ($this->service(ResumePendingInputHandler::class))(new ResumePendingInput($pending->id));

        self::assertNotContains(self::HUMAN, $this->game('g1')->game()->pendingSeats(), 'the answer cast the vote');
        $async = $this->browser->getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $async);
        self::assertContainsEquals(new RunNextActor('werewolf_game', 'g1'), array_map(static fn($e): object => $e->getMessage(), $async->getSent()), 'the resolved input wakes the game');

        $next = $this->runner()->step('g1', new ModelChoice($model->platform(), 'qwen3:8b'));

        self::assertSame(StepOutcome::Played, $next->outcome);
        self::assertFalse($next->expired);
        $game = $this->game('g1')->game();
        self::assertSame(Phase::Night, $game->phase(), 'the vote resolved and the game went on');
        self::assertFalse($game->seat(Table::ADA)->alive, 'three votes (seats 3, 5, 6) eliminated seat 1');
        self::assertNull($game->winner());
    }

    private static function vote(int $target): ToolCallResult
    {
        return new ToolCallResult([new ToolCall('c1', 'ww_vote', ['target' => $target, 'reason' => 'Suspicious.'])]);
    }

    private function runner(): NextActorRunner
    {
        return $this->service(NextActorRunner::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = $this->browser->getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }
}
