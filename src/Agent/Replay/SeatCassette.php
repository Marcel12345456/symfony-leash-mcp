<?php

declare(strict_types=1);

namespace App\Agent\Replay;

use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Test\Recording\ResultSerializer;

/**
 * One recorded agent run (decisions/033): every model request in call order, keyed by seat (Werewolf: seat number,
 * Doc Review: `author:d1`, decisions/038), plus the hash of
 * the player prompt it was recorded with and the scripted villager seat, if any (replay runs the same script). A request that failed is stored as `timeout` or `error`, so the replayed
 * turn fails the same way and the queues stay aligned.
 *
 * File: `{"model", "prompt", "scriptedSeat"?, "interactions": [{"seat", "result"} | {"seat", "timeout"} | {"seat", "error"}]}`.
 */
final class SeatCassette
{
    /** @var list<array{seat: int|string, result?: array<string, mixed>, timeout?: string, error?: string}> */
    private array $interactions = [];

    /** @param non-empty-string $model */
    private function __construct(
        public readonly string $path,
        public readonly string $model,
        public readonly string $promptHash,
        public readonly ?int $scriptedSeat = null,
    ) {}

    /** @param non-empty-string $model */
    public static function start(string $path, string $model, string $promptHash, ?int $scriptedSeat = null): self
    {
        $cassette = new self($path, $model, $promptHash, $scriptedSeat);
        $cassette->save();

        return $cassette;
    }

    public static function load(string $path, string $promptHash): self
    {
        $json = is_file($path) ? file_get_contents($path) : false;
        if (false === $json) {
            throw new \RuntimeException(\sprintf('No cassette at %s. Record one with --record and LEASH_MODEL=ollama.', $path));
        }
        /** @var array{model: non-empty-string, prompt: string, scriptedSeat?: int, interactions: list<array{seat: int|string, result?: array<string, mixed>, timeout?: string, error?: string}>} $data */
        $data = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        if ($data['prompt'] !== $promptHash) {
            throw new \RuntimeException(\sprintf('The cassette %s was recorded with other prompts; re-record it (decisions/033).', $path));
        }
        $cassette = new self($path, $data['model'], $data['prompt'], $data['scriptedSeat'] ?? null);
        $cassette->interactions = $data['interactions'];

        return $cassette;
    }

    /**
     * The actor a model request belongs to, from the turn message: a Werewolf seat number ("You are seat N.",
     * SeatTurnRunner) or a Doc Review agent per document ("You are the author. Document d1." → `author:d1`,
     * DocTurnRunner). Read from a MessageBag before the provider, the normalized payload behind it.
     *
     * @param object|array<array-key, mixed>|string $input
     */
    public static function actorOf(object|array|string $input): int|string
    {
        $text = '';
        if ($input instanceof MessageBag) {
            foreach ($input->getMessages() as $message) {
                $text .= $message instanceof UserMessage ? $message->asText() . "\n" : '';
            }
        } else {
            $text = (string) json_encode($input);
        }

        return match (true) {
            1 === preg_match('/You are seat (\d+)\./', $text, $m) => (int) $m[1],
            1 === preg_match('/You are the (\w+)\. Document ([\w-]+)\./', $text, $m) => $m[1] . ':' . $m[2],
            default => throw new \LogicException('Replay: no seat or agent in the model input.'),
        };
    }

    public function result(int|string $seat, ResultInterface $result): void
    {
        $this->interactions[] = ['seat' => $seat, 'result' => ResultSerializer::toArray($result)];
        $this->save();
    }

    public function timeout(int|string $seat, string $message): void
    {
        $this->interactions[] = ['seat' => $seat, 'timeout' => $message];
        $this->save();
    }

    public function error(int|string $seat, string $message): void
    {
        $this->interactions[] = ['seat' => $seat, 'error' => $message];
        $this->save();
    }

    /** @return array<int|string, list<array{seat: int|string, result?: array<string, mixed>, timeout?: string, error?: string}>> actor => its interactions in call order */
    public function bySeat(): array
    {
        $queues = [];
        foreach ($this->interactions as $interaction) {
            $queues[$interaction['seat']][] = $interaction;
        }

        return $queues;
    }

    private function save(): void
    {
        $directory = \dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create ' . $directory);
        }
        $data = ['model' => $this->model, 'prompt' => $this->promptHash, ...(null === $this->scriptedSeat ? [] : ['scriptedSeat' => $this->scriptedSeat]), 'interactions' => $this->interactions];
        $json = json_encode($data, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        file_put_contents($this->path, $json . "\n", \LOCK_EX);
    }
}
