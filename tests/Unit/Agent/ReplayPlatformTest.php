<?php

declare(strict_types=1);

namespace App\Tests\Unit\Agent;

use App\Agent\Replay\RecordingSeatProvider;
use App\Agent\Replay\ReplayPlatform;
use App\Agent\Replay\SeatCassette;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\AI\Platform\Test\Recording\RecordingProvider;
use Symfony\AI\Platform\Test\Recording\Cassette;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/** Record per seat, replay per seat (decisions/033). */
final class ReplayPlatformTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/leash-cassette-' . bin2hex(random_bytes(4)) . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function testRecordedAnswersReplayPerSeatInOrder(): void
    {
        $live = $this->recording([
            new ToolCallResult([new ToolCall('0', 'ww_vote', ['target' => 2])]),
            new TextResult('seat 4 speaks'),
            new TextResult('seat 1 voted'),
        ]);
        $live->invoke('qwen3:8b', self::turn(1))->getResult();
        $live->invoke('qwen3:8b', self::turn(4))->getResult();
        $live->invoke('qwen3:8b', self::turn(1))->getResult();

        $replay = new ReplayPlatform(SeatCassette::load($this->path, 'hash-1'));
        $platform = $replay->platform();

        self::assertSame(3, $replay->remaining());
        $first = $platform->invoke('qwen3:8b', self::turn(1))->getResult();
        self::assertInstanceOf(ToolCallResult::class, $first);
        self::assertSame(['target' => 2], $first->getContent()[0]->getArguments());
        self::assertSame('seat 1 voted', $platform->invoke('qwen3:8b', self::turn(1))->getResult()->getContent(), 'seat 1 gets its own answers, not seat 4\'s');
        self::assertSame('seat 4 speaks', $platform->invoke('qwen3:8b', self::turn(4))->getResult()->getContent());
        self::assertSame(0, $replay->remaining());
    }

    public function testRecordedTimeoutTimesOutAgain(): void
    {
        $live = $this->recording([new TransportException('Idle timeout reached')]);
        try {
            $live->invoke('qwen3:8b', self::turn(5))->getResult();
            self::fail('the live timeout propagates');
        } catch (TransportExceptionInterface) {
        }

        $this->expectException(TransportExceptionInterface::class);
        $this->expectExceptionMessage('Idle timeout reached');
        new ReplayPlatform(SeatCassette::load($this->path, 'hash-1'))->platform()->invoke('qwen3:8b', self::turn(5))->getResult();
    }

    public function testExhaustedSeatFailsLoudly(): void
    {
        SeatCassette::start($this->path, 'qwen3:8b', 'hash-1');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('seat 2 asked for more model answers than the cassette holds');
        new ReplayPlatform(SeatCassette::load($this->path, 'hash-1'))->platform()->invoke('qwen3:8b', self::turn(2))->getResult();
    }

    public function testCassetteFromAnotherPromptIsRefused(): void
    {
        SeatCassette::start($this->path, 'qwen3:8b', 'hash-1');

        $this->expectExceptionMessage('recorded with another player prompt');
        SeatCassette::load($this->path, 'hash-2');
    }

    public function testCassetteRemembersTheScriptedSeat(): void
    {
        SeatCassette::start($this->path, 'qwen3:8b', 'hash-1', 6);
        self::assertSame(6, SeatCassette::load($this->path, 'hash-1')->scriptedSeat);

        SeatCassette::start($this->path, 'qwen3:8b', 'hash-1');
        self::assertNull(SeatCassette::load($this->path, 'hash-1')->scriptedSeat);
    }

    /** Pins BUGS.md: symfony/ai's RecordingProvider gives two different MessageBags the same default signature. */
    public function testUpstreamDefaultSignatureIsContentBlind(): void
    {
        $cassette = new Cassette($this->path);
        $platform = new Platform([new RecordingProvider(MockPlatformFactory::createProvider(['m' => 'answer']), $cassette, record: true)]);
        $platform->invoke('m', new MessageBag(Message::ofUser('You are seat 1.')))->getResult();
        $platform->invoke('m', new MessageBag(Message::ofUser('You are seat 6. Something else entirely.')))->getResult();

        $data = json_decode((string) file_get_contents($this->path), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        /** @var list<array{signature: string}> $interactions */
        $interactions = $data['interactions'];
        self::assertCount(2, $interactions);
        self::assertSame($interactions[0]['signature'], $interactions[1]['signature']);
    }

    /** @param list<TextResult|ToolCallResult|\Throwable> $answers live answers in call order */
    private function recording(array $answers): PlatformInterface
    {
        $live = MockPlatformFactory::createProvider(static function () use (&$answers): TextResult|ToolCallResult {
            $next = array_shift($answers) ?? throw new \LogicException('script exhausted');

            return $next instanceof \Throwable ? throw $next : $next;
        });

        return new Platform([new RecordingSeatProvider($live, SeatCassette::start($this->path, 'qwen3:8b', 'hash-1'))]);
    }

    private static function turn(int $seat): MessageBag
    {
        return new MessageBag(Message::forSystem('rules'), Message::ofUser(\sprintf('You are seat %d. It is your turn.', $seat)));
    }
}
