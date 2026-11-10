<?php

declare(strict_types=1);

namespace App\Spike;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\TokenUsage\TokenUsageAggregation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Phase 0 Proof 2: one Symfony AI agent call that must use one tool. Exit 0 only if the tool was called and the answer uses it. */
#[AsCommand('spike:llm-tool', 'Proof 2: chat completion with one tool call through Symfony AI')]
final readonly class SpikeLlmToolCommand
{
    public function __construct(
        #[Autowire(service: 'ai.agent.spike')]
        private AgentInterface $agent,
        private CaseAmountTool $tool,
        #[Autowire('%env(LEASH_SPIKE_MODEL)%')]
        private string $model,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Append the qwen3 soft switch /no_think to the question')]
        bool $noThink = false,
    ): int {
        $question = 'What is the order amount of case C1 in euros?' . ($noThink ? ' /no_think' : '');
        $result = $this->agent->call(new MessageBag(Message::ofUser($question)));
        $answer = $result->getContent();
        $answer = \is_string($answer) ? $answer : get_debug_type($answer);

        $io->writeln('model: ' . $this->model);
        $io->writeln('tool calls: [' . implode(', ', $this->tool->calls) . ']');
        $io->writeln('answer: ' . $answer);
        $usage = $result->getMetadata()->get('token_usage');
        // Ollama reports prompt and completion tokens but no total (BUGS.md), so the total is computed here.
        $tokens = $usage instanceof TokenUsageAggregation ? ($usage->getPromptTokens() ?? 0) + ($usage->getCompletionTokens() ?? 0) : 0;
        if ($usage instanceof TokenUsageAggregation) {
            $io->writeln(\sprintf(
                'token usage: %d model calls, prompt %d, completion %d, total %d',
                \count($usage->getTokenUsages()),
                $usage->getPromptTokens() ?? 0,
                $usage->getCompletionTokens() ?? 0,
                $tokens,
            ));
        }

        $failures = [];
        if ($this->tool->calls !== ['C1']) {
            $failures[] = 'expected exactly one tool call with caseId C1';
        }
        if (0 === $tokens) {
            $failures[] = 'no token usage reported';
        }
        if (!str_contains(str_replace(',', '.', $answer), '42.5')) {
            $failures[] = 'answer does not use the tool result (42.50 EUR)';
        }
        foreach ($failures as $failure) {
            $io->error($failure);
        }

        return [] === $failures ? Command::SUCCESS : Command::FAILURE;
    }
}
