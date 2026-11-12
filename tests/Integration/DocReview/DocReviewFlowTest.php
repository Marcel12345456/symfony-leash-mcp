<?php

declare(strict_types=1);

namespace App\Tests\Integration\DocReview;

use App\DocReview\Entity\DocEvent;
use App\DocReview\Entity\Document;
use App\DocReview\Security\DocReviewCallers;
use App\Tests\Integration\Support\KernelBrowserPsr18Client;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Leash\HumanInput\Client\InputRequired;
use Leash\HumanInput\Client\RawMcpClient;
use Leash\HumanInput\Client\ToolCall;
use Mcp\Client;
use Mcp\Client\Transport\HttpTransport;
use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Tool;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * M6 done-signal (PLAN §3.6, decisions/036): Doc Review over the real MCP endpoint. The editor's sign-off is MRTR:
 * publish asks first and changes nothing, approve (with an edited headline) publishes once, a decline with a note
 * opens `doc_send_back`, which carries the note to the author. The author never sees the rubric or reviewer tools.
 */
final class DocReviewFlowTest extends WebTestCase
{
    private const string SECRET = '$ecretf0rt3st';

    private KernelBrowser $browser;
    private RawMcpClient $raw;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $em = $this->entityManager();
        $schema = new SchemaTool($em);
        $schema->dropDatabase();
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());
        $em->persist(new Document('d1', 'Release notes for Quill 2.0 (fictional): new plugin API, Node 18 dropped.'));
        $em->flush();
        $em->clear();
        $factory = new Psr17Factory();
        $this->raw = new RawMcpClient(new KernelBrowserPsr18Client($this->browser, $factory), $factory, $factory);
    }

    public function testAuthorNeverSeesTheRubricOrReviewerTools(): void
    {
        $author = $this->connect(DocReviewCallers::AUTHOR);
        self::assertSame(['doc_submit_draft'], self::toolNames($author));
        self::assertSame(['brief', 'draft', 'feedback'], self::resourceNames($author));
        self::assertFalse($author->callTool('doc_submit_draft', ['title' => 'Quill 2.0', 'body' => 'Draft.'])->isError);
        self::assertSame([], self::toolNames($author));
        self::assertSame(['draft'], self::resourceNames($author));
        $author->disconnect();

        $reviewer = $this->connect(DocReviewCallers::REVIEWER);
        self::assertSame(['doc_request_changes', 'doc_approve'], self::toolNames($reviewer));
        self::assertContains('rubric', self::resourceNames($reviewer));
        $reviewer->disconnect();
    }

    public function testPublishAsksFirstThenAnEditedHeadlineIsPublishedOnce(): void
    {
        $this->toApproved();

        $asked = $this->publish();
        self::assertInstanceOf(InputRequired::class, $asked);
        self::assertSame('approved', $this->document()->getStatus(), 'no side effect before the answer');
        self::assertNull($this->document()->headline());

        $answer = ['approval' => ['action' => 'accept', 'content' => ['approve' => true, 'headline' => 'Quill 2.0: a new plugin API']]];
        $published = $this->publish($answer, $asked->requestState);
        $again = $this->publish($answer, $asked->requestState);

        self::assertInstanceOf(CallToolResult::class, $published);
        self::assertSame('published', $this->document()->getStatus());
        self::assertSame('Quill 2.0: a new plugin API', $this->document()->headline());
        self::assertInstanceOf(CallToolResult::class, $again);
        self::assertEquals($published->structuredContent, $again->structuredContent, 'a duplicate retry replays the stored result');
        self::assertCount(1, $this->events('transition_applied', 'publish'));
    }

    public function testADeclineWithANoteReachesTheAuthorThroughSendBack(): void
    {
        $this->toApproved();
        $asked = $this->publish();
        self::assertInstanceOf(InputRequired::class, $asked);

        $declined = $this->publish(['approval' => ['action' => 'accept', 'content' => ['approve' => false, 'note' => 'Add upgrade notes for Node 18 users.']]], $asked->requestState);

        self::assertInstanceOf(CallToolResult::class, $declined);
        self::assertSame('approved', $this->document()->getStatus());
        self::assertSame(['doc_publish', 'doc_send_back'], \is_array($declined->structuredContent) ? $declined->structuredContent['availableTools'] ?? null : null);

        $reviewer = $this->connect(DocReviewCallers::REVIEWER);
        self::assertFalse($reviewer->callTool('doc_send_back', [])->isError);
        $reviewer->disconnect();

        $author = $this->connect(DocReviewCallers::AUTHOR);
        self::assertSame('drafting', $this->document()->getStatus());
        self::assertSame([['from' => 'editor', 'text' => 'Add upgrade notes for Node 18 users.']], $this->read($author, 'feedback')['feedback'] ?? null);
        $author->disconnect();
    }

    private function toApproved(): void
    {
        $author = $this->connect(DocReviewCallers::AUTHOR);
        self::assertFalse($author->callTool('doc_submit_draft', ['title' => 'Quill 2.0', 'body' => 'New plugin API.'])->isError);
        $author->disconnect();
        $reviewer = $this->connect(DocReviewCallers::REVIEWER);
        self::assertFalse($reviewer->callTool('doc_approve', [])->isError);
        $reviewer->disconnect();
    }

    /** @param array<string, array<string, mixed>>|null $inputResponses */
    private function publish(?array $inputResponses = null, ?string $requestState = null): InputRequired|CallToolResult
    {
        $call = new ToolCall('http://localhost/mcp/doc_review/d1', 'doc_review:d1', DocReviewCallers::REVIEWER, 'doc_publish', ['headline' => 'Quill 2.0 released']);

        return $this->raw->call($call, DocReviewCallers::token(self::SECRET, DocReviewCallers::REVIEWER), $inputResponses, $requestState);
    }

    private function connect(string $caller): Client
    {
        $factory = new Psr17Factory();
        $client = Client::builder()->setClientInfo('leash-docreview-test', '0.1')->build();
        $client->connect(new HttpTransport(
            'http://localhost/mcp/doc_review/d1',
            ['Authorization' => 'Bearer ' . DocReviewCallers::token(self::SECRET, $caller)],
            new KernelBrowserPsr18Client($this->browser, $factory),
            $factory,
            $factory,
        ));

        return $client;
    }

    /** @return list<string> */
    private static function toolNames(Client $client): array
    {
        return array_values(array_map(static fn(Tool $t): string => $t->name, $client->listTools()->tools));
    }

    /** @return list<string> */
    private static function resourceNames(Client $client): array
    {
        return array_values(array_map(static fn(ResourceDefinition $r): string => $r->name, $client->listResources()->resources));
    }

    /** @return array<array-key, mixed> */
    private function read(Client $client, string $name): array
    {
        $content = $client->readResource('doc://d1/' . $name)->contents[0] ?? null;
        self::assertInstanceOf(TextResourceContents::class, $content);
        $data = json_decode($content->text, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    private function document(): Document
    {
        $em = $this->entityManager();
        $em->clear();

        return $em->find(Document::class, 'd1') ?? throw new \LogicException('No document d1');
    }

    /** @return list<DocEvent> */
    private function events(string $type, string $transition): array
    {
        return array_values(array_filter(
            $this->entityManager()->getRepository(DocEvent::class)->findBy(['documentId' => 'd1']),
            static fn(DocEvent $e): bool => $e->type->value === $type && ($e->payload['transition'] ?? null) === $transition,
        ));
    }

    private function entityManager(): EntityManagerInterface
    {
        $em = $this->browser->getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }
}
