<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp;

use App\Tests\Unit\Lib\WorkflowMcp\Fixture\Document;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\DocumentWorkflow;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\Harness;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\ServerPsr18Client;
use Leash\WorkflowMcp\Client\McpClientToolbox;
use Leash\WorkflowMcp\Client\TurnBudgetToolbox;
use Leash\WorkflowMcp\Event\CapabilityDenied;
use Leash\WorkflowMcp\Event\ToolCalled;
use Mcp\Client;
use Mcp\Client\Transport\HttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\Security\Core\User\InMemoryUser;

/** The agent toolbox over a real `Mcp\Client` talking HTTP to the synthetic document server in-process. */
final class McpClientToolboxTest extends TestCase
{
    private Harness $harness;
    private Client $client;

    protected function setUp(): void
    {
        $this->harness = new Harness();
    }

    protected function tearDown(): void
    {
        if (isset($this->client)) {
            $this->client->disconnect();
        }
    }

    public function testListsVisibleToolsWithBareNamesAndNullSchemaWithoutProperties(): void
    {
        $tools = $this->toolbox(new Document('review'), 'ROLE_EDITOR')->getTools();
        $byName = array_combine(array_map(static fn(Tool $t): string => $t->getName(), $tools), $tools);

        self::assertSame(['doc_approve', 'doc_reject', 'doc_read_comments', 'doc_add_comment'], array_keys($byName));
        self::assertSame(['note'], array_keys($byName['doc_approve']->getParameters()['properties'] ?? []));
        self::assertNull($byName['doc_reject']->getParameters());
    }

    public function testSuccessReturnsStructuredContentAsJson(): void
    {
        $document = new Document('review');

        $result = $this->toolbox($document, 'ROLE_EDITOR')->execute(new ToolCall('c1', 'doc_approve', ['note' => 'fine']));

        self::assertSame('{"state":"published","availableTools":["doc_archive"],"message":"Applied \"doc_approve\"."}', $result->getResult());
        self::assertSame('published', $document->getStatus());
    }

    public function testHiddenToolReachesTheServerAndComesBackAsText(): void
    {
        $document = new Document('review');

        $result = $this->toolbox($document, 'ROLE_AUTHOR')->execute(new ToolCall('c1', 'doc_approve', ['note' => 'sneaky']));

        self::assertSame('Error: "doc_approve" is not available to you in state "review": your role cannot use it here, or your turn is over. Available now: doc_read_comments.', $result->getResult());
        self::assertCount(1, $this->harness->eventsOf(CapabilityDenied::class));
        self::assertSame('review', $document->getStatus());
    }

    public function testTransportFailureBecomesTextNotAnException(): void
    {
        $toolbox = $this->toolbox(new Document('review'), 'ROLE_EDITOR');
        $this->client->disconnect();

        $result = $toolbox->execute(new ToolCall('c1', 'doc_reject'));

        self::assertIsString($result->getResult());
        self::assertStringStartsWith('Error: "doc_reject" failed', $result->getResult());
        unset($this->client);
    }

    public function testBudgetStopsCallsBeforeTheServer(): void
    {
        $budget = new TurnBudgetToolbox($this->toolbox(new Document('review'), 'ROLE_EDITOR'), 1);

        $budget->execute(new ToolCall('c1', 'doc_add_comment'));
        $second = $budget->execute(new ToolCall('c2', 'doc_add_comment'));

        self::assertSame(1, $budget->calls());
        self::assertSame('Error: the tool budget for this turn is spent (1 calls). Do not call more tools; end your turn.', $second->getResult());
        self::assertCount(1, $this->harness->eventsOf(ToolCalled::class));
    }

    private function toolbox(Document $document, string $role): McpClientToolbox
    {
        $factory = new Psr17Factory();
        $builder = $this->harness->builder(DocumentWorkflow::create(), $document, new InMemoryUser('bob', null, [$role]));
        $this->client = Client::builder()->setClientInfo('leash-toolbox-test', '0.1')->build();
        $this->client->connect(new HttpTransport('http://localhost/mcp/document/d1', [], new ServerPsr18Client($builder), $factory, $factory));

        return new McpClientToolbox($this->client);
    }
}
