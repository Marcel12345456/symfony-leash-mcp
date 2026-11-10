<?php

declare(strict_types=1);

namespace App\Agent\Werewolf;

use App\Werewolf\Rules\Audience;
use App\Werewolf\Rules\Event;
use App\Werewolf\Rules\Game;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\ToolCallMessage;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Appends a readable Markdown transcript per game to `var/werewolf/<gameId>.md` (decisions/032): each seat turn with
 * what the seat saw, its tool calls and their results, its final text; the last line names the result.
 * The system prompt is the same for every turn and is left out.
 */
final readonly class TranscriptWriter
{
    public function __construct(
        #[Autowire('%leash.werewolf.transcripts%')]
        private string $directory,
    ) {}

    public function path(string $gameId): string
    {
        return $this->directory . '/' . $gameId . '.md';
    }

    public function turn(string $gameId, Game $game, SeatTurn $turn): void
    {
        $lines = [\sprintf(
            '## Day %d, %s: seat %d (%s, %d tool %s)',
            $game->day(),
            $game->phase()->value,
            $turn->seat,
            $turn->outcome->value,
            $turn->toolCalls,
            1 === $turn->toolCalls ? 'call' : 'calls',
        ), ''];

        foreach ($turn->messages->getMessages() as $message) {
            $lines = [...$lines, ...match (true) {
                $message instanceof UserMessage => ['<details><summary>What the seat saw</summary>', '', (string) $message->asText(), '', '</details>', ''],
                $message instanceof AssistantMessage => self::assistant($message),
                $message instanceof ToolCallMessage => [\sprintf('- ← `%s`: %s', $message->getToolCall()->getName(), self::oneLine((string) $message->asText()))],
                default => [],
            }];
        }
        if (null !== $turn->finalText) {
            $lines = [...self::paragraph($lines), '> ' . self::oneLine($turn->finalText)];
        }
        if (null !== $turn->error) {
            $lines = [...self::paragraph($lines), '**Turn ended:** ' . self::oneLine($turn->error)];
        }

        $this->append($gameId, $lines);
    }

    /** Closes the transcript; the last line names the result (or says the game is unfinished). */
    public function finish(string $gameId, Game $game): void
    {
        $winner = $game->winner();
        $this->append($gameId, [
            '## Public log',
            '',
            ...array_map(
                static fn(Event $event): string => \sprintf('- Day %d, %s: %s', $event->day, $event->phase->value, $event->text),
                array_values(array_filter($game->events(), static fn(Event $event): bool => Audience::Public === $event->audience)),
            ),
            '',
            null === $winner ? \sprintf('Result: unfinished (day %d, %s)', $game->day(), $game->phase()->value) : 'Result: ' . $winner->value,
        ]);
    }

    /** @return list<string> */
    private static function assistant(AssistantMessage $message): array
    {
        $lines = [];
        if ($message->hasToolCalls()) {
            foreach ($message->getToolCalls() as $call) {
                $lines[] = \sprintf('- → `%s` %s', $call->getName(), json_encode($call->getArguments(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
            }
        }
        $text = $message->asText();
        if (null !== $text && '' !== $text) {
            $lines[] = '> ' . self::oneLine($text);
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     *
     * @return list<string> the lines, ending in exactly one blank line
     */
    private static function paragraph(array $lines): array
    {
        return '' === end($lines) ? $lines : [...$lines, ''];
    }

    private static function oneLine(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /** @param list<string> $lines */
    private function append(string $gameId, array $lines): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Cannot create ' . $this->directory);
        }
        $separator = is_file($this->path($gameId)) ? "\n" : '';
        file_put_contents($this->path($gameId), $separator . implode("\n", $lines) . "\n", \FILE_APPEND | \LOCK_EX);
    }
}
