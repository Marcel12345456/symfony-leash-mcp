<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Dashboard\WorkflowDiagram;
use App\DocReview\Entity\Document;
use App\DocReview\Security\DocReviewCallers;
use App\Tests\Integration\Support\KernelBrowserPsr18Client;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Leash\HumanInput\Client\InputRequired;
use Leash\HumanInput\Client\RawMcpClient;
use Leash\HumanInput\Client\ToolCall;
use Leash\HumanInput\Pending\PendingInput;
use Leash\HumanInput\Pending\PendingInputs;
use Mcp\Client;
use Mcp\Client\Transport\HttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * M7 done-signal (3), PLAN §3.7: the Doc Review tab shows each document's workflow diagram with the current place
 * highlighted, and one timeline whose kinds drive the colours: denied (red), requested (amber), transition (green).
 */
final class DashboardDocReviewTest extends WebTestCase
{
    private const string SECRET = '$ecretf0rt3st';

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $em = $this->entityManager();
        $schema = new SchemaTool($em);
        $schema->dropDatabase();
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());
        $em->persist(new Document('d1', 'Release notes for Quill 2.0 (fictional).'));
        $em->persist(new Document('d2', 'Migration guide.'));
        $em->flush();
        $em->clear();
    }

    public function testDiagramHighlightsTheCurrentPlaceAndTheTimelineHasEveryKind(): void
    {
        $author = $this->connect(DocReviewCallers::AUTHOR);
        self::assertFalse($author->callTool('doc_submit_draft', ['title' => 'Quill 2.0', 'body' => 'New plugin API.'])->isError);
        $author->disconnect();
        $reviewer = $this->connect(DocReviewCallers::REVIEWER);
        self::assertTrue($reviewer->callTool('doc_submit_draft', ['title' => 'x', 'body' => 'y'])->isError);
        self::assertFalse($reviewer->callTool('doc_approve', [])->isError);
        $reviewer->disconnect();
        $factory = new Psr17Factory();
        $raw = new RawMcpClient(new KernelBrowserPsr18Client($this->browser, $factory), $factory, $factory);
        $call = new ToolCall('http://localhost/mcp/doc_review/d1', 'doc_review:d1', DocReviewCallers::REVIEWER, 'doc_publish', ['headline' => 'Quill 2.0 released']);
        $asked = $raw->call($call, DocReviewCallers::token(self::SECRET, DocReviewCallers::REVIEWER));
        self::assertInstanceOf(InputRequired::class, $asked);
        // The raw client only asks; the HumanInput handler stores the pending input. Store it the same way here.
        $pending = $this->browser->getContainer()->get(PendingInputs::class);
        self::assertInstanceOf(PendingInputs::class, $pending);
        $pending->save(new PendingInput($call, $asked, new \DateTimeImmutable('+1 minute')));

        $documents = $this->documents();
        self::assertSame(['d1', 'd2'], array_column($documents, 'id'));
        [$d1, $d2] = $documents;

        self::assertSame('approved', $d1['status']);
        self::assertStringStartsWith('graph LR', $d1['diagram']);
        self::assertSame(1, preg_match('/(place\d+)\(\("approved"\)\)/', $d1['diagram'], $approved), $d1['diagram']);
        self::assertStringEndsWith(\sprintf("\nstyle %s %s", $approved[1] ?? '?', WorkflowDiagram::CURRENT_STYLE), $d1['diagram']);

        $kinds = array_column($d1['timeline'], 'kind');
        self::assertContains('transition', $kinds);
        self::assertContains('denied', $kinds);
        $denied = self::first('denied', $d1['timeline']);
        self::assertSame(DocReviewCallers::REVIEWER, $denied['actor']);
        self::assertStringContainsString('doc_submit_draft denied in in_review', $denied['text']);
        $transition = self::first('transition', $d1['timeline']);
        self::assertSame(DocReviewCallers::AUTHOR, $transition['actor']);
        self::assertSame('submit_draft → in_review', $transition['text']);
        $requested = self::first('requested', $d1['timeline']);
        self::assertSame(DocReviewCallers::REVIEWER, $requested['actor']);
        self::assertSame('doc_publish waits for a human (pending)', $requested['text']);
        self::assertSame('requested', end($kinds), 'the timeline is in time order');

        self::assertSame('drafting', $d2['status']);
        self::assertSame([], $d2['timeline']);
    }

    public function testDashboardPageHasBothTabsAndTheLocalMermaid(): void
    {
        $crawler = $this->browser->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSame(['Werewolf', 'Doc Review'], $crawler->filter('[role=tab]')->each(static fn($n): string => $n->text()));
        self::assertCount(1, $crawler->filter('script[src="/vendor/mermaid.min.js"]'));
        self::assertFileExists(\dirname(__DIR__, 3) . '/public/vendor/mermaid.min.js');
    }

    /** @return list<array{id: string, status: string, diagram: string, timeline: list<array{kind: string, actor: string, text: string}>}> */
    private function documents(): array
    {
        $this->browser->request('GET', '/dashboard/doc-review.json');
        self::assertResponseIsSuccessful();
        /** @var array{documents: list<array{id: string, status: string, diagram: string, timeline: list<array{kind: string, actor: string, text: string}>}>} $json JSON of DocReviewBoard::view() */
        $json = json_decode((string) $this->browser->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $json['documents'];
    }

    /**
     * @param list<array{kind: string, actor: string, text: string}> $timeline
     *
     * @return array{kind: string, actor: string, text: string}
     */
    private static function first(string $kind, array $timeline): array
    {
        foreach ($timeline as $entry) {
            if ($kind === $entry['kind']) {
                return $entry;
            }
        }
        self::fail('No timeline entry of kind ' . $kind);
    }

    private function connect(string $caller): Client
    {
        $factory = new Psr17Factory();
        $client = Client::builder()->setClientInfo('leash-dashboard-test', '0.1')->build();
        $client->connect(new HttpTransport(
            'http://localhost/mcp/doc_review/d1',
            ['Authorization' => 'Bearer ' . DocReviewCallers::token(self::SECRET, $caller)],
            new KernelBrowserPsr18Client($this->browser, $factory),
            $factory,
            $factory,
        ));

        return $client;
    }

    private function entityManager(): EntityManagerInterface
    {
        $em = $this->browser->getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }
}
