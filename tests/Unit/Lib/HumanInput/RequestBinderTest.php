<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\HumanInput;

use Leash\HumanInput\RequestState\RejectionReason;
use Leash\HumanInput\RequestState\RequestBinder;
use Leash\HumanInput\RequestState\RequestStateKey;
use Leash\HumanInput\RequestState\RequestStateRejected;
use Mcp\Server\Stateless\RequestStateCodec;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class RequestBinderTest extends TestCase
{
    private const array ARGS = ['note' => 'ok', 'amount' => 1800, 'tags' => ['a', 'b']];

    private MockClock $clock;
    private RequestBinder $binder;
    private RequestStateCodec $codec;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-07 12:00:00');
        $this->binder = new RequestBinder($this->clock);
        $this->codec = new RequestStateCodec(RequestStateKey::fromSecret('test-secret'), RequestBinder::DEFAULT_TTL);
    }

    public function testSignedStateRoundTripsAndVerifies(): void
    {
        $issued = $this->binder->issue('doc:1', 'doc_publish', self::ARGS, 'alice');
        $payload = $this->codec->verify($this->codec->mint($issued->toArray(), $this->now()), $this->now());

        // Same arguments in a different key order still match.
        $verified = $this->binder->verify($payload, 'doc:1', 'doc_publish', ['amount' => 1800, 'tags' => ['a', 'b'], 'note' => 'ok'], 'alice');

        self::assertEquals($issued, $verified);
        self::assertSame(32, \strlen($issued->nonce));
    }

    public function testEveryIssueGetsAFreshNonce(): void
    {
        self::assertNotSame(
            $this->binder->issue('doc:1', 'doc_publish', self::ARGS, 'alice')->nonce,
            $this->binder->issue('doc:1', 'doc_publish', self::ARGS, 'alice')->nonce,
        );
    }

    public function testTamperedStateIsRejectedBySdkCodec(): void
    {
        $state = $this->codec->mint($this->binder->issue('doc:1', 'doc_publish', self::ARGS, 'alice')->toArray(), $this->now());
        [$body, $mac] = explode('.', $state);
        $forged = rtrim(strtr(base64_encode(str_replace('alice', 'mallo', (string) base64_decode(strtr($body, '-_', '+/'), true))), '+/', '-_'), '=');

        $this->expectException(\Throwable::class);
        $this->codec->verify($forged . '.' . $mac, $this->now());
    }

    public function testStateFromAnotherKeyIsRejectedBySdkCodec(): void
    {
        $other = new RequestStateCodec(RequestStateKey::fromSecret('other-secret'), RequestBinder::DEFAULT_TTL);
        $state = $other->mint($this->binder->issue('doc:1', 'doc_publish', self::ARGS, 'alice')->toArray(), $this->now());

        $this->expectException(\Throwable::class);
        $this->codec->verify($state, $this->now());
    }

    /** @return iterable<string, array{array<mixed>, string, string, array<mixed>, string, RejectionReason}> */
    public static function rejections(): iterable
    {
        yield 'malformed payload' => [['nonce' => 'x'], 'doc:1', 'doc_publish', self::ARGS, 'alice', RejectionReason::Tampered];
        yield 'other subject' => [[], 'doc:2', 'doc_publish', self::ARGS, 'alice', RejectionReason::WrongTarget];
        yield 'other tool' => [[], 'doc:1', 'doc_reject', self::ARGS, 'alice', RejectionReason::WrongTarget];
        yield 'wrong caller' => [[], 'doc:1', 'doc_publish', self::ARGS, 'bob', RejectionReason::WrongCaller];
        yield 'changed arguments' => [[], 'doc:1', 'doc_publish', ['amount' => 9999] + self::ARGS, 'alice', RejectionReason::ArgumentsMismatch];
        yield 'list order matters' => [[], 'doc:1', 'doc_publish', ['tags' => ['b', 'a']] + self::ARGS, 'alice', RejectionReason::ArgumentsMismatch];
    }

    /**
     * @param array<mixed> $override  payload to use instead of the issued one (empty: issued)
     * @param array<mixed> $arguments
     */
    #[DataProvider('rejections')]
    public function testRejectsMismatch(array $override, string $subjectRef, string $capability, array $arguments, string $callerId, RejectionReason $reason): void
    {
        $payload = [] === $override ? $this->binder->issue('doc:1', 'doc_publish', self::ARGS, 'alice')->toArray() : $override;

        $this->assertRejected($reason, fn() => $this->binder->verify($payload, $subjectRef, $capability, $arguments, $callerId));
    }

    public function testRejectsOwnExpiry(): void
    {
        $payload = $this->binder->issue('doc:1', 'doc_publish', self::ARGS, 'alice', 60)->toArray();
        $this->clock->sleep(60);

        $this->assertRejected(RejectionReason::Expired, fn() => $this->binder->verify($payload, 'doc:1', 'doc_publish', self::ARGS, 'alice'));
    }

    public function testEmptySecretIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RequestStateKey::fromSecret('');
    }

    private function assertRejected(RejectionReason $reason, callable $verify): void
    {
        try {
            $verify();
            self::fail('Expected a rejection: ' . $reason->value);
        } catch (RequestStateRejected $e) {
            self::assertSame($reason, $e->reason);
            self::assertSame($reason->message(), $e->getMessage());
        }
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
