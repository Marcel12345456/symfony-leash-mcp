<?php

declare(strict_types=1);

namespace App\Tests\Integration\Agent;

use App\Agent\Message\RunNextActor;
use App\Agent\DocReview\DocWorkRunner;
use App\Agent\MessageHandler\RunNextActorHandler;
use App\Agent\ModelChoiceFactory;
use App\Agent\Werewolf\NextActorRunner;
use App\Tests\Integration\Werewolf\WerewolfMcpCase;
use App\Werewolf\Rules\Game;
use App\Werewolf\Rules\Phase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * RunNextActor with the test env's unreachable Ollama: every turn fails at once, so each step runs on the
 * deadline path alone (no model, no network).
 */
final class RunNextActorHandlerTest extends WerewolfMcpCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createGame('g1', Game::arranged(7, self::ROLES));
    }

    public function testPlayedStepQueuesTheNextOne(): void
    {
        $this->handler()(new RunNextActor('werewolf_game', 'g1'));

        self::assertNotSame(Phase::Night, $this->game('g1')->game()->phase(), 'silent seats missed the deadline, the night resolved');
        self::assertEquals([new RunNextActor('werewolf_game', 'g1')], $this->queued());
    }

    public function testMissingGameStopsTheChain(): void
    {
        $this->handler()(new RunNextActor('werewolf_game', 'nope'));

        self::assertSame([], $this->queued());
    }

    public function testWorkflowWithoutAgentsIsLeftAlone(): void
    {
        $this->handler()(new RunNextActor('no_agents_workflow', 'x1'));

        self::assertSame([], $this->queued());
        self::assertSame(Phase::Night, $this->game('g1')->game()->phase());
    }

    public function testReplayModeLeavesTheGameToThePlayingProcess(): void
    {
        $models = new ModelChoiceFactory('replay', 'http://127.0.0.1:9', 'qwen3:latest', true, 2.0, \dirname(__DIR__, 3) . '/config/prompts');
        $handler = new RunNextActorHandler($this->service(NextActorRunner::class), $this->service(DocWorkRunner::class), $models, $this->service(MessageBusInterface::class), new NullLogger());

        $handler(new RunNextActor('werewolf_game', 'g1'));
        $handler(new RunNextActor('doc_review', 'd1'));

        self::assertSame([], $this->queued());
        self::assertSame(Phase::Night, $this->game('g1')->game()->phase(), 'no turn, no deadline');
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

    private function handler(): RunNextActorHandler
    {
        $handler = $this->browser->getContainer()->get(RunNextActorHandler::class);
        self::assertInstanceOf(RunNextActorHandler::class, $handler);

        return $handler;
    }

    /** @return list<object> */
    private function queued(): array
    {
        $transport = $this->browser->getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return array_values(array_map(static fn(Envelope $e): object => $e->getMessage(), [...$transport->getSent()]));
    }
}
