<?php

declare(strict_types=1);

namespace App\Agent\Replay;

use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Test\Recording\ResultSerializer;

/**
 * One recorded Werewolf game (decisions/033): every model request in call order, keyed by seat, plus the hash of
 * the player prompt it was recorded with. A request that failed is stored as `timeout` or `error`, so the replayed
 * turn fails the same way and the queues stay aligned.
 *
 * File: `{"model", "prompt", "interactions": [{"seat", "result"} | {"seat", "timeout"} | {"seat", "error"}]}`.
 */
final class SeatCassette
{
    /** @var list<array{seat: int, result?: array<string, mixed>, timeout?: string, error?: string}> */
    private array $interactions = [];

    /** @param non-empty-string $model */
    private function __construct(
        public readonly string $path,
        public readonly string $model,
        public readonly string $promptHash,
    ) {}

    /** @param non-empty-string $model */
    public static function start(string $path, string $model, string $promptHash): self
    {
        $cassette = new self($path, $model, $promptHash);
        $cassette->save();

        return $cassette;
    }

    public static function load(string $path, string $promptHash): self
    {
        $json = is_file($path) ? file_get_contents($path) : false;
        if (false === $json) {
            throw new \RuntimeException(\sprintf('No Werewolf cassette at %s. Record one with --record and LEASH_MODEL=ollama.', $path));
        }
        /** @var array{model: non-empty-string, prompt: string, interactions: list<array{seat: int, result?: array<string, mixed>, timeout?: string, error?: string}>} $data */
        $data = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        if ($data['prompt'] !== $promptHash) {
            throw new \RuntimeException(\sprintf('The cassette %s was recorded with another player prompt; re-record it (decisions/033).', $path));
        }
        $cassette = new self($path, $data['model'], $data['prompt']);
        $cassette->interactions = $data['interactions'];

        return $cassette;
    }

    /**
     * The seat a model request belongs to, from the turn message ("You are seat N", SeatTurnRunner): a MessageBag
     * before the provider, the normalized payload behind it (MockPlatformFactory closures).
     *
     * @param object|array<array-key, mixed>|string $input
     */
    public static function seatOf(object|array|string $input): int
    {
        $text = '';
        if ($input instanceof MessageBag) {
            foreach ($input->getMessages() as $message) {
                $text .= $message instanceof UserMessage ? $message->asText() . "\n" : '';
            }
        } else {
            $text = (string) json_encode($input);
        }

        return 1 === preg_match('/You are seat (\d+)\./', $text, $m) ? (int) $m[1] : throw new \LogicException('Replay: no seat in the model input.');
    }

    public function result(int $seat, ResultInterface $result): void
    {
        $this->interactions[] = ['seat' => $seat, 'result' => ResultSerializer::toArray($result)];
        $this->save();
    }

    public function timeout(int $seat, string $message): void
    {
        $this->interactions[] = ['seat' => $seat, 'timeout' => $message];
        $this->save();
    }

    public function error(int $seat, string $message): void
    {
        $this->interactions[] = ['seat' => $seat, 'error' => $message];
        $this->save();
    }

    /** @return array<int, list<array{seat: int, result?: array<string, mixed>, timeout?: string, error?: string}>> seat => its interactions in call order */
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
        $json = json_encode(['model' => $this->model, 'prompt' => $this->promptHash, 'interactions' => $this->interactions], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        file_put_contents($this->path, $json . "\n", \LOCK_EX);
    }
}
