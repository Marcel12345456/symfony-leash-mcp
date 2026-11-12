<?php

declare(strict_types=1);

namespace App\Agent\Replay;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\Test\Recording\ResultSerializer;
use Symfony\Component\HttpClient\Exception\TransportException;

/**
 * Replays a SeatCassette (decisions/033): each seat gets its recorded answers first in, first out; no model, no
 * network. Recorded failures fail again the same way. An exhausted seat fails loudly: the game diverged.
 */
final class ReplayPlatform
{
    /** @var array<int, list<array{seat: int, result?: array<string, mixed>, timeout?: string, error?: string}>> */
    private array $queues;

    public function __construct(SeatCassette $cassette)
    {
        $this->queues = $cassette->bySeat();
    }

    public function platform(): PlatformInterface
    {
        return MockPlatformFactory::createPlatform(fn(mixed $model, object|array|string $input): ResultInterface => $this->next(SeatCassette::seatOf($input)));
    }

    /** @return int requests left, per seat (0 everywhere once the game replayed to the end) */
    public function remaining(): int
    {
        return array_sum(array_map(\count(...), $this->queues));
    }

    private function next(int $seat): ResultInterface
    {
        $this->queues[$seat] ??= [];
        $interaction = array_shift($this->queues[$seat]) ?? throw new \LogicException(\sprintf('Replay: seat %d asked for more model answers than the cassette holds; the game diverged from the recording.', $seat));

        return match (true) {
            isset($interaction['timeout']) => throw new TransportException($interaction['timeout']),
            isset($interaction['error']) => throw new RuntimeException($interaction['error']),
            isset($interaction['result']) => ResultSerializer::fromArray($interaction['result']),
            default => throw new \LogicException('Replay: empty cassette entry for seat ' . $seat),
        };
    }
}
