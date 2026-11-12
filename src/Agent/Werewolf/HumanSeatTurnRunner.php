<?php

declare(strict_types=1);

namespace App\Agent\Werewolf;

use App\Agent\McpConnector;
use App\Agent\TurnOutcome;
use App\Werewolf\Security\WerewolfCallers;
use Leash\HumanInput\Client\HumanInputToolCaller;
use Leash\HumanInput\Client\McpCallFailed;
use Leash\HumanInput\Client\ToolCall;
use Leash\WorkflowMcp\Client\McpClientToolbox;
use Mcp\Schema\Tool;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Plays the human seat's turn (PLAN §3.5, decisions/035): no model. The seat calls its one acting tool with empty
 * arguments; the server asks for the missing fields (`input_required`), which becomes a pending input in the inbox.
 * The human's answer resumes the call, and the game goes on.
 */
final readonly class HumanSeatTurnRunner
{
    public function __construct(
        private McpConnector $connector,
        private HumanInputToolCaller $humanInput,
        #[Autowire('%env(LEASH_WEREWOLF_SECRET)%')]
        #[\SensitiveParameter]
        private string $secret,
    ) {}

    public function play(string $gameId, int $seat): SeatTurn
    {
        $caller = WerewolfCallers::seat($seat);
        $path = '/mcp/werewolf_game/' . $gameId;
        $client = $this->connector->connect($path, WerewolfCallers::token($this->secret, $caller), 'leash-' . $caller);
        try {
            $acting = array_values(array_filter($client->listTools()->tools, static fn(Tool $t): bool => false === $t->annotations?->readOnlyHint));
        } finally {
            $client->disconnect();
        }

        $messages = new MessageBag(Message::ofUser(\sprintf('Seat %d is played by a human.', $seat)));
        $tool = $acting[0] ?? null;
        if (null === $tool) {
            return new SeatTurn($seat, $messages, 'Nothing to do.', 0, TurnOutcome::Done);
        }

        try {
            $result = $this->humanInput->call(new ToolCall($this->connector->url($path), NextActorRunner::WORKFLOW . ':' . $gameId, $caller, $tool->name, []));
        } catch (McpCallFailed $e) {
            return new SeatTurn($seat, $messages, null, 1, TurnOutcome::Failed, $e->getMessage());
        }

        return new SeatTurn($seat, $messages, \sprintf('%s: %s', $tool->name, McpClientToolbox::render($result)), 1, TurnOutcome::Done);
    }
}
