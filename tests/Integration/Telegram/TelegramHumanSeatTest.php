<?php

declare(strict_types=1);

namespace App\Tests\Integration\Telegram;

use App\Agent\ModelChoice;
use App\Agent\Werewolf\NextActorRunner;
use App\Agent\Werewolf\StepOutcome;
use App\Telegram\TelegramCallbackOutcome;
use App\Telegram\TelegramCallbacks;
use App\Telegram\TelegramPoller;
use App\Tests\Integration\Support\ScriptedSeatPlatform;
use App\Tests\Integration\Werewolf\WerewolfMcpCase;
use App\Tests\Unit\Werewolf\Rules\Table;
use Leash\HumanInput\Client\InputRequired;
use Leash\HumanInput\Client\ToolCall;
use Leash\HumanInput\Pending\HumanInputNotifier;
use Leash\HumanInput\Pending\PendingInput;
use Leash\HumanInput\Pending\PendingInputs;
use Leash\HumanInput\Pending\PendingInputStatus;
use Leash\HumanInput\Pending\ResumePendingInput;
use Leash\HumanInput\Pending\ResumePendingInputHandler;
use Symfony\AI\Platform\Result\ToolCall as ModelToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Notifier\Event\MessageEvent;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * M6 Telegram (decisions/034): the human seat's vote goes out with one button per target; a press in the
 * configured chat (4242, `.env.test`) answers the pending input, and the resume casts the vote. Bot API calls
 * go to a mock HttpClient; the bridge sends to `null://null` and the notifier events are read back.
 */
final class TelegramHumanSeatTest extends WerewolfMcpCase
{
    private const int CHAT = 4242;

    /** @var list<array{method: string, body: array<mixed>}> */
    private array $calls = [];

    /** @var list<array<mixed>> the next `getUpdates` result */
    private array $updates = [];

    protected function setUp(): void
    {
        parent::setUp();
        $game = Table::game();
        Table::night($game, [Table::ADA => Table::DAN, Table::BEN => Table::DAN]);
        Table::discuss($game);
        $this->createGame('g1', $game);
        $this->game('g1')->setHumanSeat(Table::CLEO);
        $this->entityManager()->flush();

        $http = $this->browser->getContainer()->get('test.telegram_http'); // @phpstan-ignore symfonyContainer.serviceNotFound (defined only in when@test)
        self::assertInstanceOf(MockHttpClient::class, $http);
        $http->setResponseFactory(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode(\is_string($options['body'] ?? null) ? $options['body'] : '{}', true);
            self::assertStringStartsWith('https://api.telegram.org/bot123:test-token/', $url);
            $this->calls[] = ['method' => basename($url), 'body' => \is_array($body) ? $body : []];
            $result = 'getUpdates' === basename($url) ? $this->updates : true;
            $this->updates = [];

            return new MockResponse(json_encode(['ok' => true, 'result' => $result], \JSON_THROW_ON_ERROR));
        });
    }

    public function testButtonPressResumesTheVote(): void
    {
        $pending = $this->pauseForTheHuman();

        [$event] = array_values(array_filter(self::getNotifierEvents(), static fn(MessageEvent $e): bool => !$e->isQueued()));
        $message = $event->getMessage();
        self::assertStringContainsString('#' . $pending->id, $message->getSubject());
        $markup = $message->getOptions()?->toArray()['reply_markup'] ?? null;
        self::assertIsArray($markup);
        self::assertIsArray($markup['inline_keyboard'] ?? null);
        $buttons = array_merge(...array_filter($markup['inline_keyboard'], \is_array(...)));
        self::assertSame([$pending->id . ':0', $pending->id . ':1', $pending->id . ':2', $pending->id . ':3'], array_column($buttons, 'callback_data'), 'one button per other living seat (1, 2, 5, 6)');
        self::assertSame('HTML', $message->getOptions()?->toArray()['parse_mode'] ?? null);

        $callbacks = $this->service(TelegramCallbacks::class);
        self::assertSame(TelegramCallbackOutcome::Ignored, $callbacks->handle(self::press($pending->id . ':0', chat: 999, from: 999)));
        self::assertSame(TelegramCallbackOutcome::Ignored, $callbacks->handle(self::press($pending->id . ':0', from: 999)), 'right chat, other user');
        self::assertSame([], $this->calls, 'foreign presses get no reply');
        self::assertSame(TelegramCallbackOutcome::Unknown, $callbacks->handle(self::press('ffffffffffff:0')));
        self::assertSame(TelegramCallbackOutcome::Unknown, $callbacks->handle(self::press($pending->id . ':9')));
        self::assertSame(PendingInputStatus::Pending, $this->pendingInput($pending->id)->status);

        $this->calls = [];
        self::assertSame(TelegramCallbackOutcome::Answered, $callbacks->handle(self::press($pending->id . ':0')));
        self::assertSame(PendingInputStatus::Answered, $this->pendingInput($pending->id)->status);
        $calls = $this->calls();
        self::assertSame(['answerCallbackQuery', 'editMessageReplyMarkup'], array_column($calls, 'method'));
        self::assertSame(['chat_id' => (string) self::CHAT, 'message_id' => 77, 'reply_markup' => ['inline_keyboard' => []]], $calls[1]['body'] ?? null, 'the keyboard is removed');
        $async = $this->browser->getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $async);
        self::assertContainsEquals(new ResumePendingInput($pending->id), array_map(static fn($e): object => $e->getMessage(), $async->getSent()));

        self::assertSame(TelegramCallbackOutcome::AlreadyAnswered, $callbacks->handle(self::press($pending->id . ':1')), 'a second press changes nothing');
        self::assertSame(PendingInputStatus::Answered, $this->pendingInput($pending->id)->status);

        ($this->service(ResumePendingInputHandler::class))(new ResumePendingInput($pending->id));

        self::assertNotContains(Table::CLEO, $this->game('g1')->game()->pendingSeats(), 'the button cast the vote');
        self::assertSame(PendingInputStatus::Resolved, $this->pendingInput($pending->id)->status);
    }

    public function testPollerHandlesOneBatchAndAdvancesTheOffset(): void
    {
        $pending = $this->pauseForTheHuman();
        $this->updates = [['update_id' => 7, 'callback_query' => self::press($pending->id . ':3')]];

        self::assertSame(8, $this->service(TelegramPoller::class)->poll(0, 0));
        self::assertSame(0, $this->calls[0]['body']['offset']);
        self::assertSame(['callback_query'], $this->calls[0]['body']['allowed_updates']);
        self::assertSame(PendingInputStatus::Answered, $this->pendingInput($pending->id)->status);

        self::assertSame(8, $this->service(TelegramPoller::class)->poll(8, 0));
        self::assertSame(8, $this->calls[3]['body']['offset'], 'the next poll confirms the batch');
    }

    public function testInputThatDoesNotFitOnButtonsGetsTheInboxLink(): void
    {
        $pending = new PendingInput(
            new ToolCall('http://localhost/mcp/document/d1', 'document:d1', 'author', 'doc_publish'),
            new InputRequired(['signoff' => ['method' => 'elicitation/create', 'params' => ['message' => 'Publish "A_b*c"?', 'requestedSchema' => ['type' => 'object', 'properties' => [
                'approve' => ['type' => 'boolean'],
                'note' => ['type' => 'string'],
            ]]]]], null),
            new \DateTimeImmutable(),
        );

        $this->service(HumanInputNotifier::class)->notify($pending);

        [$event] = array_values(array_filter(self::getNotifierEvents(), static fn(MessageEvent $e): bool => !$e->isQueued()));
        self::assertStringContainsString('/inbox/', $event->getMessage()->getSubject());
        self::assertArrayNotHasKey('reply_markup', $event->getMessage()->getOptions()?->toArray() ?? []);
    }

    /** @return list<array{method: string, body: array<mixed>}> */
    private function calls(): array
    {
        return $this->calls;
    }

    private function pauseForTheHuman(): PendingInput
    {
        $model = new ScriptedSeatPlatform([1 => [self::vote(5), 'Voted.'], 2 => [self::vote(5), 'Voted.'], 5 => [self::vote(1), 'Voted.'], 6 => [self::vote(1), 'Voted.']]);
        self::assertSame(StepOutcome::Paused, $this->service(NextActorRunner::class)->step('g1', new ModelChoice($model->platform(), 'qwen3:8b'))->outcome);
        [$pending] = $this->service(PendingInputs::class)->withStatus(PendingInputStatus::Pending);

        return $pending;
    }

    private function pendingInput(string $id): PendingInput
    {
        $this->entityManager()->clear();

        return $this->service(PendingInputs::class)->get($id) ?? throw new \LogicException('No pending input ' . $id);
    }

    /** @return array<string, mixed> */
    private static function press(string $data, int $chat = self::CHAT, int $from = self::CHAT): array
    {
        return ['id' => 'cb-' . $data, 'from' => ['id' => $from], 'message' => ['message_id' => 77, 'chat' => ['id' => $chat]], 'data' => $data];
    }

    private static function vote(int $target): ToolCallResult
    {
        return new ToolCallResult([new ModelToolCall('c1', 'ww_vote', ['target' => $target, 'reason' => 'Suspicious.'])]);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = $this->browser->getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }
}
