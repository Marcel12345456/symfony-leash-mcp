<?php

declare(strict_types=1);

namespace App\Agent\DocReview;

use App\Agent\McpConnector;
use App\Agent\ModelChoice;
use App\DocReview\Security\DocReviewCallers;
use Leash\HumanInput\Client\HumanInputToolCaller;
use Leash\WorkflowMcp\Client\HumanInputToolbox;
use Leash\WorkflowMcp\Client\McpClientToolbox;
use Leash\WorkflowMcp\Client\TurnBudgetToolbox;
use Mcp\Client;
use Mcp\Schema\Content\TextResourceContents;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Exception\ExceptionInterface as AgentException;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * One author or reviewer turn on one document: connect as that agent, read every visible resource into the turn
 * message, offer the visible tools (at most three calls). Calls go through HumanInputToolCaller, so the editor's
 * sign-off becomes a pending input and the turn ends.
 */
final readonly class DocTurnRunner
{
    public const int TOOL_BUDGET = 3;

    public function __construct(
        private McpConnector $connector,
        private HumanInputToolCaller $humanInput,
        #[Autowire('%kernel.secret%')]
        #[\SensitiveParameter]
        private string $secret,
        #[Autowire('%kernel.project_dir%/config/prompts/docreview')]
        private string $prompts,
    ) {}

    /** @return string|null the agent's closing sentence, or null when the turn failed */
    public function play(string $documentId, string $caller, ModelChoice $model): ?string
    {
        $path = '/mcp/doc_review/' . $documentId;
        $client = $this->connector->connect($path, DocReviewCallers::token($this->secret, $caller), 'leash-' . $caller);
        try {
            $role = DocReviewCallers::AUTHOR === $caller ? 'author' : 'reviewer';
            $messages = new MessageBag(Message::forSystem($this->prompt($role)), Message::ofUser(self::turnMessage($role, $documentId, self::resources($client))));
            $toolbox = new TurnBudgetToolbox(new HumanInputToolbox(new McpClientToolbox($client), $this->humanInput, $this->connector->url($path), 'doc_review:' . $documentId, $caller), self::TOOL_BUDGET);
            $agent = new Agent($model->platform, $model->model, name: $role, toolbox: $toolbox, maxToolCalls: self::TOOL_BUDGET + 1);
            try {
                $content = $agent->call($messages, $model->options())->getContent();

                return \is_string($content) ? $content : '';
            } catch (TransportExceptionInterface|AgentException|PlatformException) {
                return null;
            }
        } finally {
            $client->disconnect();
        }
    }

    /** @return array<string, string> */
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
    private static function turnMessage(string $role, string $documentId, array $resources): string
    {
        $parts = [\sprintf('You are the %s. Document %s. Here is everything you can see right now.', $role, $documentId)];
        foreach ($resources as $name => $text) {
            $parts[] = \sprintf("## %s\n```json\n%s\n```", $name, $text);
        }
        $parts[] = 'Act now with the tools you have, then answer with one short sentence about what you did.';

        return implode("\n\n", $parts);
    }

    private function prompt(string $role): string
    {
        $prompt = file_get_contents($this->prompts . '/' . $role . '.md');

        return false === $prompt ? throw new \RuntimeException('Missing prompt ' . $role) : $prompt;
    }
}
