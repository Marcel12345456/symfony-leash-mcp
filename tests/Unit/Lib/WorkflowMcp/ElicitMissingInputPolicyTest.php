<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp;

use App\Tests\Unit\Lib\WorkflowMcp\Fixture\Document;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\DocumentWorkflow;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\Harness;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\RateInput;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\StatelessClient;
use Leash\HumanInput\RequestState\RejectionReason;
use Leash\WorkflowMcp\Event\ToolCalled;
use Leash\WorkflowMcp\Tool\ElicitMissingInputPolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * The elicit-missing step on the synthetic workflow (decisions/035): `doc_rate` is a place tool with required
 * `stars` (pick list from the tool) and `summary`; a human-backed rater may call it with missing fields.
 */
final class ElicitMissingInputPolicyTest extends TestCase
{
    private const array HUMAN = ['ROLE_RATER', ElicitMissingInputPolicy::HUMAN_BACKED];
    private const array AGENT = ['ROLE_RATER'];

    private Harness $harness;
    private Document $document;

    protected function setUp(): void
    {
        $this->harness = new Harness();
        $this->document = new Document('review');
    }

    public function testHumanBackedCallerIsAskedForExactlyTheMissingFields(): void
    {
        $result = $this->client('hana', self::HUMAN)->call('doc_rate', ['summary' => 'Clear.']);

        self::assertSame('input_required', $result['resultType'] ?? null);
        self::assertIsString($result['requestState'] ?? null);
        $schema = StatelessClient::at($result, 'inputRequests', ElicitMissingInputPolicy::KEY, 'params', 'requestedSchema');
        self::assertSame(['stars'], StatelessClient::at($schema, 'required'));
        self::assertSame(['stars'], array_keys((array) StatelessClient::at($schema, 'properties')));
        self::assertSame(
            [['const' => '3', 'title' => 'three stars'], ['const' => '4', 'title' => 'four stars'], ['const' => '5', 'title' => 'five stars']],
            StatelessClient::at($schema, 'properties', 'stars', 'oneOf'),
        );
        self::assertSame('"doc_rate" needs: stars.', StatelessClient::at($result, 'inputRequests', ElicitMissingInputPolicy::KEY, 'params', 'message'));
        self::assertSame([], $this->harness->rate->ratings, 'nothing applied before the answer');
        self::assertSame(0, $this->harness->subjects->saves);
        self::assertSame([], $this->harness->eventsOf(ToolCalled::class));
    }

    public function testEmptyCallAsksForEveryRequiredFieldButNotTheOptionalOne(): void
    {
        $result = $this->client('hana', self::HUMAN)->call('doc_rate');

        $schema = StatelessClient::at($result, 'inputRequests', ElicitMissingInputPolicy::KEY, 'params', 'requestedSchema');
        self::assertSame(['stars', 'summary'], StatelessClient::at($schema, 'required'));
        self::assertSame(['type' => 'string', 'title' => 'summary', 'description' => 'One sentence on why'], StatelessClient::at($schema, 'properties', 'summary'));
    }

    public function testFullArgumentsAreAppliedWithoutAQuestion(): void
    {
        $result = $this->client('hana', self::HUMAN)->call('doc_rate', ['stars' => 4, 'summary' => 'Clear.']);

        self::assertSame(['rated' => 4, 'by' => 'hana'], $result['structuredContent'] ?? null);
        self::assertEquals([new RateInput(4, 'Clear.')], $this->harness->rate->ratings);
    }

    public function testAnswerIsMergedTypedAndAppliedOnce(): void
    {
        $args = ['summary' => 'Clear.'];
        $state = $this->ask($args);
        $answer = ['action' => 'accept', 'content' => ['stars' => '5']];

        $first = $this->retry('hana', $state, $answer, $args);
        $second = $this->retry('hana', $state, $answer, $args);

        self::assertSame(['rated' => 5, 'by' => 'hana'], $first['structuredContent'] ?? null);
        self::assertSame(StatelessClient::at($first, 'structuredContent'), StatelessClient::at($second, 'structuredContent'), 'a duplicate retry replays the stored result');
        self::assertEquals([new RateInput(5, 'Clear.')], $this->harness->rate->ratings, 'applied once');
        self::assertSame(1, $this->harness->subjects->saves);
        self::assertCount(1, $this->harness->eventsOf(ToolCalled::class));
    }

    public function testDeclinedQuestionChangesNothing(): void
    {
        $result = $this->retry('hana', $this->ask([]), ['action' => 'decline'], []);

        self::assertFalse($result['isError'] ?? true);
        self::assertSame('No answer: "doc_rate" was not called; nothing changed.', StatelessClient::text($result));
        self::assertSame([], $this->harness->rate->ratings);
    }

    public function testAnswerOutsideTheRulesIsAValidationError(): void
    {
        $result = $this->retry('hana', $this->ask(['summary' => 'Meh.']), ['action' => 'accept', 'content' => ['stars' => '9']], ['summary' => 'Meh.']);

        self::assertTrue($result['isError'] ?? null);
        self::assertStringStartsWith('Invalid input for "doc_rate": stars:', StatelessClient::text($result));
        self::assertSame([], $this->harness->rate->ratings);
    }

    public function testRetryByAnotherCallerIsRejected(): void
    {
        $result = $this->retry('olaf', $this->ask([]), ['action' => 'accept', 'content' => ['stars' => '4', 'summary' => 'x']], []);

        self::assertSame(RejectionReason::WrongCaller->message(), StatelessClient::text($result));
        self::assertSame([], $this->harness->rate->ratings);
    }

    public function testHumanBackedCallerSeesNoRequiredListAgentsDo(): void
    {
        self::assertNull(StatelessClient::at($this->rateSchema(self::HUMAN), 'required'));
        self::assertSame(['stars', 'summary'], StatelessClient::at($this->rateSchema(self::AGENT), 'required'));
    }

    public function testAgentWithMissingFieldsGetsTheSdkError(): void
    {
        $response = $this->client('ann', self::AGENT)->request('tools/call', ['name' => 'doc_rate', 'arguments' => (object) ['summary' => 'Clear.']]);

        self::assertSame(-32602, StatelessClient::at($response, 'error', 'code'));
        self::assertSame([], $this->harness->rate->ratings);
    }

    /** @param list<string> $roles */
    private function rateSchema(array $roles): mixed
    {
        $tools = array_filter($this->client('x', $roles)->listTools(), static fn(array $t): bool => 'doc_rate' === $t['name']);

        return StatelessClient::at(array_values($tools)[0] ?? [], 'inputSchema');
    }

    /** @param array<string, mixed> $arguments */
    private function ask(array $arguments): string
    {
        $state = $this->client('hana', self::HUMAN)->call('doc_rate', $arguments)['requestState'] ?? null;
        self::assertIsString($state);

        return $state;
    }

    /**
     * @param array<string, mixed> $answer
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private function retry(string $caller, string $state, array $answer, array $arguments): array
    {
        $response = $this->client($caller, self::HUMAN)->request('tools/call', [
            'name' => 'doc_rate',
            'arguments' => (object) $arguments,
            'inputResponses' => [ElicitMissingInputPolicy::KEY => $answer],
            'requestState' => $state,
        ]);
        $result = $response['result'] ?? null;
        self::assertIsArray($result, 'Expected a result, got ' . json_encode($response));

        return array_combine(array_map(strval(...), array_keys($result)), $result);
    }

    /** @param list<string> $roles */
    private function client(string $caller, array $roles): StatelessClient
    {
        return $this->harness->client(DocumentWorkflow::create(rating: true), $this->document, new InMemoryUser($caller, null, $roles));
    }
}
