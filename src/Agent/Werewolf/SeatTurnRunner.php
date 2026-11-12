<?php

declare(strict_types=1);

namespace App\Agent\Werewolf;

use App\Agent\McpConnector;
use App\Agent\ModelChoice;
use App\Agent\TurnOutcome;
use App\Werewolf\Rules\Phase;
use App\Werewolf\Security\WerewolfCallers;
use Leash\WorkflowMcp\Client\McpClientToolbox;
use Leash\WorkflowMcp\Client\TurnBudgetToolbox;
use Mcp\Client;
use Mcp\Schema\Content\TextResourceContents;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Exception\ExceptionInterface as AgentException;
use Symfony\AI\Agent\Exception\MaxIterationsExceededException;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Plays one seat's turn as one agent run (decisions/029): connect as the seat, read every visible resource into
 * the turn message (decisions/027), offer the seat's visible tools with a budget of three calls, and keep the
 * conversation. Whatever goes wrong ends the turn without action; the moderator's deadline handles the rest.
 */
final readonly class SeatTurnRunner
{
    public const int TOOL_BUDGET = 3;

    public function __construct(
        private McpConnector $connector,
        #[Autowire('%env(LEASH_WEREWOLF_SECRET)%')]
        #[\SensitiveParameter]
        private string $secret,
        #[Autowire('%kernel.project_dir%/config/prompts/werewolf/player.md')]
        private string $promptFile,
        private ?float $toolTimeoutSeconds = 30.0,
    ) {}

    public function play(string $gameId, int $seat, ModelChoice $model, int $day, Phase $phase): SeatTurn
    {
        $model = $model->forSeat($seat, $phase);
        $caller = WerewolfCallers::seat($seat);
        $client = $this->connector->connect('/mcp/werewolf_game/' . $gameId, WerewolfCallers::token($this->secret, $caller), 'leash-' . $caller);

        try {
            $messages = new MessageBag(Message::forSystem($this->prompt()), Message::ofUser(self::turnMessage($seat, $day, $phase, self::resources($client))));
            $toolbox = new TurnBudgetToolbox(new McpClientToolbox($client, $this->toolTimeoutSeconds), self::TOOL_BUDGET);
            $agent = new Agent($model->platform, $model->model, name: 'seat-' . $seat, toolbox: $toolbox, maxToolCalls: self::TOOL_BUDGET + 1);

            [$final, $outcome, $error] = [null, TurnOutcome::Done, null];
            try {
                $content = $agent->call($messages, $model->options())->getContent();
                $final = \is_string($content) ? $content : null;
            } catch (TransportExceptionInterface $e) {
                [$outcome, $error] = [TurnOutcome::TimedOut, $e->getMessage()];
            } catch (MaxIterationsExceededException $e) {
                [$outcome, $error] = [TurnOutcome::BudgetSpent, $e->getMessage()];
            } catch (AgentException|PlatformException $e) {
                [$outcome, $error] = [TurnOutcome::Failed, $e->getMessage()];
            }

            return new SeatTurn($seat, $messages, $final, $toolbox->calls(), $outcome, $error);
        } finally {
            $client->disconnect();
        }
    }

    /** @return array<string, string> resource name => text, only what this seat may read */
    private static function resources(Client $client): array
    {
        $texts = [];
        foreach ($client->listResources()->resources as $resource) {
            $content = $client->readResource($resource->uri)->contents[0] ?? null;
            if ($content instanceof TextResourceContents) {
                $texts[$resource->name] = $content->text;
            }
        }

        return $texts;
    }

    /** @param array<string, string> $resources */
    private static function turnMessage(int $seat, int $day, Phase $phase, array $resources): string
    {
        $parts = [\sprintf('You are seat %d. %s It is your turn. Here is everything you can see right now.', $seat, self::situation($day, $phase))];
        foreach ($resources as $name => $text) {
            $parts[] = \sprintf("## %s\n```json\n%s\n```", $name, $text);
        }
        $parts[] = \sprintf('Act now with the tools you have (at most %d calls), then answer with one short sentence about what you did.', self::TOOL_BUDGET);

        return implode("\n\n", $parts);
    }

    /** Says where the game stands, so the model does not invent votes or deaths that have not happened yet. */
    private static function situation(int $day, Phase $phase): string
    {
        return match ($phase) {
            Phase::Night => \sprintf('It is night %d.', $day),
            Phase::Dawn => \sprintf('It is dawn of day %d.', $day),
            Phase::DayDiscussion => \sprintf('It is day %d, discussion. Nobody has voted yet today; the vote comes after the discussion. Make your one statement now.', $day),
            Phase::DayVote => \sprintf('It is day %d, the vote. Cast your vote with a short public reason.', $day),
            Phase::Over => 'The game is over.',
        };
    }

    private function prompt(): string
    {
        $prompt = file_get_contents($this->promptFile);

        return false === $prompt ? throw new \RuntimeException('Missing prompt ' . $this->promptFile) : $prompt;
    }
}
