<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\RefundDesk\Entity\CaseEvent;
use App\RefundDesk\Entity\CaseEventType;
use App\RefundDesk\Entity\RefundCase;
use App\Tests\Integration\Support\KernelBrowserPsr18Client;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Mcp\Client;
use Mcp\Client\Transport\HttpTransport;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Tool;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * M1 done-signal: the refund endpoint through the real mcp/sdk client, in-process HTTP, bearer tokens, seeded DB.
 */
final class RefundMcpEndpointTest extends WebTestCase
{
    private const string INVESTIGATOR = 'test-investigator-token';
    private const string RESOLVER = 'test-resolver-token';

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $em = $this->entityManager();
        $schema = new SchemaTool($em);
        $schema->dropDatabase();
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());
        $seed = new CommandTester(new Application($this->browser->getKernel())->find('leash:demo:seed'));
        $seed->execute([]);
        $seed->assertCommandIsSuccessful();
    }

    public function testInvestigatorSeesOnlyStartThenTheInvestigatingTools(): void
    {
        $investigator = $this->connect(self::INVESTIGATOR, 'C1');
        self::assertSame(['refund_start_investigation'], self::toolNames($investigator));

        $result = $investigator->callTool('refund_start_investigation', []);
        self::assertFalse($result->isError, self::text($result));
        self::assertSame('investigating', self::structured($result)['state'] ?? null);
        $investigator->disconnect();

        // A new connection lists for the new state (the server is built per request).
        $again = $this->connect(self::INVESTIGATOR, 'C1');
        $names = self::toolNames($again);
        sort($names);
        self::assertSame(['refund_get_customer_history', 'refund_get_order', 'refund_propose_resolution', 'refund_search_policy'], $names);
        $again->disconnect();
    }

    public function testResolverSeesOnlyTheReadOnlyPolicySearchWhileInvestigating(): void
    {
        $this->startInvestigation('C1');

        $resolver = $this->connect(self::RESOLVER, 'C1');
        $tools = $resolver->listTools()->tools;

        self::assertSame(['refund_search_policy'], array_map(static fn(Tool $t): string => $t->name, $tools));
        self::assertTrue($tools[0]->annotations?->readOnlyHint);
        $resolver->disconnect();
    }

    public function testInvisibleToolGivesActionableErrorLogsCapabilityDeniedAndChangesNothing(): void
    {
        $this->startInvestigation('C1');

        $resolver = $this->connect(self::RESOLVER, 'C1');
        $result = $resolver->callTool('refund_propose_resolution', ['decision' => 'refund', 'amountCents' => 1200, 'rationale' => 'I am not the investigator.']);
        $resolver->disconnect();

        self::assertTrue($result->isError);
        self::assertSame('"refund_propose_resolution" is not available to you in state "investigating": your role cannot use it here, or your turn is over. Available now: refund_search_policy.', self::text($result));
        self::assertSame('investigating', $this->case('C1')->getStatus());

        $denied = $this->events('C1', CaseEventType::CapabilityDenied);
        self::assertCount(1, $denied);
        self::assertSame('agent:resolver', $denied[0]->getActor());
        self::assertSame('refund_propose_resolution', $denied[0]->getPayload()['tool'] ?? null);
    }

    public function testInvalidProposalReturnsValidatorMessagesAsActionableError(): void
    {
        $this->startInvestigation('C1');

        $investigator = $this->connect(self::INVESTIGATOR, 'C1');
        $result = $investigator->callTool('refund_propose_resolution', ['decision' => 'refund', 'amountCents' => 0, 'rationale' => '']);
        $investigator->disconnect();

        self::assertTrue($result->isError);
        $text = self::text($result);
        self::assertStringContainsString('rationale: This value should not be blank.', $text);
        self::assertStringContainsString('amountCents: A refund needs an amount above 0 cents.', $text);
        self::assertStringContainsString('call "refund_propose_resolution" again', $text);
        self::assertSame('investigating', $this->case('C1')->getStatus());
    }

    public function testValidProposalMovesTheCaseToProposedForTheResolver(): void
    {
        $this->startInvestigation('C1');

        $investigator = $this->connect(self::INVESTIGATOR, 'C1');
        $result = $investigator->callTool('refund_propose_resolution', ['decision' => 'refund', 'amountCents' => 1200, 'rationale' => 'Damaged on arrival, within policy.']);
        $investigator->disconnect();

        self::assertFalse($result->isError, self::text($result));
        self::assertSame(['state' => 'proposed', 'availableTools' => [], 'message' => 'Applied "refund_propose_resolution".'], self::structured($result));
        self::assertSame(1200, $this->case('C1')->getProposedAmountCents());
        self::assertCount(2, $this->events('C1', CaseEventType::TransitionApplied));

        $resolver = $this->connect(self::RESOLVER, 'C1');
        $names = self::toolNames($resolver);
        $resolver->disconnect();
        self::assertSame(['refund_issue', 'refund_revise', 'refund_get_proposal', 'refund_search_policy'], $names);
    }

    private function startInvestigation(string $caseId): void
    {
        $client = $this->connect(self::INVESTIGATOR, $caseId);
        self::assertFalse($client->callTool('refund_start_investigation', [])->isError);
        $client->disconnect();
    }

    private function connect(string $token, string $caseId): Client
    {
        $factory = new Psr17Factory();
        $client = Client::builder()->setClientInfo('leash-test', '0.1')->build();
        $client->connect(new HttpTransport(
            'http://localhost/mcp/refund_case/' . $caseId,
            ['Authorization' => 'Bearer ' . $token],
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

    private static function text(CallToolResult $result): string
    {
        $content = $result->content[0] ?? null;

        return $content instanceof TextContent && \is_string($content->text) ? $content->text : '';
    }

    /** @return array<array-key, mixed> */
    private static function structured(CallToolResult $result): array
    {
        return \is_array($result->structuredContent) ? $result->structuredContent : [];
    }

    private function case(string $id): RefundCase
    {
        $em = $this->entityManager();
        $em->clear();

        return $em->find(RefundCase::class, $id) ?? throw new \LogicException('No case ' . $id);
    }

    /** @return list<CaseEvent> */
    private function events(string $caseId, CaseEventType $type): array
    {
        $em = $this->entityManager();
        $em->clear();

        return $em->getRepository(CaseEvent::class)->findBy(['case' => $caseId, 'type' => $type]);
    }

    private function entityManager(): EntityManagerInterface
    {
        $em = $this->browser->getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }
}
