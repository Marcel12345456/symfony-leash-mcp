<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp;

use App\Tests\Unit\Lib\WorkflowMcp\Fixture\Document;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\DocumentWorkflow;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\Harness;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\StatelessClient;
use Leash\WorkflowMcp\Event\CapabilityDenied;
use Leash\WorkflowMcp\Event\ToolCalled;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

/** The per-request MCP server over the synthetic workflow, driven in-process (stateless wire). */
final class WorkflowServerTest extends TestCase
{
    private Harness $harness;

    protected function setUp(): void
    {
        $this->harness = new Harness();
    }

    public function testListsOnlyVisibleToolsWithAnnotationsAndSchemas(): void
    {
        $tools = $this->client(new Document('review'), 'ROLE_EDITOR')->listTools();
        $byName = array_column($tools, null, 'name');

        self::assertSame(['doc_approve', 'doc_reject', 'doc_read_comments', 'doc_add_comment'], array_column($tools, 'name'));
        self::assertSame(['type' => 'object', 'properties' => ['note' => ['type' => 'string']], 'required' => ['note'], 'additionalProperties' => false], StatelessClient::at($byName, 'doc_approve', 'inputSchema'));
        self::assertSame(['state', 'availableTools', 'message'], StatelessClient::at($byName, 'doc_approve', 'outputSchema', 'required'));
        self::assertTrue(StatelessClient::at($byName, 'doc_read_comments', 'annotations', 'readOnlyHint'));
        self::assertFalse(StatelessClient::at($byName, 'doc_add_comment', 'annotations', 'readOnlyHint'));
    }

    public function testTransitionToolAppliesPersistsAndReturnsStructuredResult(): void
    {
        $document = new Document('review');

        $result = $this->client($document, 'ROLE_EDITOR')->call('doc_approve', ['note' => 'looks good']);

        self::assertFalse($result['isError'] ?? false);
        self::assertSame(['state' => 'published', 'availableTools' => ['doc_archive'], 'message' => 'Applied "doc_approve".'], $result['structuredContent'] ?? null);
        self::assertSame('published', $document->getStatus());
        self::assertSame(1, $this->harness->subjects->saves);
        self::assertCount(1, $this->harness->eventsOf(ToolCalled::class));
    }

    public function testInvisibleToolGivesActionableErrorAndCapabilityDenied(): void
    {
        $document = new Document('review');

        $result = $this->client($document, 'ROLE_AUTHOR')->call('doc_approve', ['note' => 'sneaky']);

        self::assertTrue($result['isError'] ?? null);
        self::assertSame('"doc_approve" is not available to your role in state "review". Available now: doc_read_comments.', StatelessClient::text($result));
        self::assertSame('review', $document->getStatus());
        self::assertSame(0, $this->harness->subjects->saves);
        $denied = $this->harness->eventsOf(CapabilityDenied::class);
        self::assertCount(1, $denied);
        self::assertSame('doc_approve', $denied[0]->tool);
        self::assertSame(['doc_read_comments'], $denied[0]->available);
    }

    public function testUnknownToolIsAnsweredTheSameWay(): void
    {
        $result = $this->client(new Document('archived'), 'ROLE_EDITOR')->call('doc_nonsense');

        self::assertTrue($result['isError'] ?? null);
        self::assertStringContainsString('No tools are available to you now.', StatelessClient::text($result));
    }

    public function testConstraintViolationIsAnActionableToolError(): void
    {
        $document = new Document('review');

        $result = $this->client($document, 'ROLE_EDITOR')->call('doc_approve', ['note' => '']);

        self::assertTrue($result['isError'] ?? null);
        self::assertStringContainsString('note: This value should not be blank.', StatelessClient::text($result));
        self::assertStringContainsString('call "doc_approve" again', StatelessClient::text($result));
        self::assertSame('review', $document->getStatus());
    }

    /** Shape errors are rejected by the SDK's inputSchema validation before the tool runs (decisions/015). */
    public function testWrongTypeIsAJsonRpcInvalidParamsErrorFromTheSdk(): void
    {
        $response = $this->client(new Document('review'), 'ROLE_EDITOR')->request('tools/call', ['name' => 'doc_approve', 'arguments' => ['note' => 42]]);

        self::assertSame(-32602, StatelessClient::at($response, 'error', 'code'));
    }

    public function testPlaceToolReturnsStructuredContent(): void
    {
        $result = $this->client(new Document('review'), 'ROLE_AUTHOR')->call('doc_read_comments');

        self::assertFalse($result['isError'] ?? false);
        self::assertSame(['comments' => []], $result['structuredContent'] ?? null);
        self::assertSame(0, $this->harness->subjects->saves, 'read-only place tools do not persist');
    }

    private function client(Document $document, string $role): StatelessClient
    {
        return new StatelessClient($this->harness->servers->create(DocumentWorkflow::create(), $document, new InMemoryUser('bob', null, [$role])));
    }
}
