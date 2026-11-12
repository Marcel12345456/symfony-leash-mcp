<?php

declare(strict_types=1);

namespace App\Tests\Integration\Lib;

use App\DocReview\Security\DocReviewCallers;
use App\Tests\Integration\Support\KernelBrowserPsr18Client;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Leash\HumanInput\Client\McpCallFailed;
use Leash\HumanInput\Client\RawMcpClient;
use Leash\HumanInput\Client\ToolCall;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The raw client against the real endpoint over in-process HTTP (decisions/021): the SDK header
 * validator accepts its first calls and its retries; HTTP and JSON-RPC errors become McpCallFailed.
 * The full `input_required` round trip over HTTP is DocReview\DocReviewFlowTest.
 */
final class RawMcpClientEndpointTest extends WebTestCase
{
    private const string SECRET = '$ecretf0rt3st';

    private RawMcpClient $client;

    protected function setUp(): void
    {
        $browser = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $schema = new SchemaTool($em);
        $schema->dropDatabase();
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());
        $seed = new CommandTester(new Application($browser->getKernel())->find('leash:demo:seed'));
        $seed->execute([]);
        $seed->assertCommandIsSuccessful();

        $factory = new Psr17Factory();
        $this->client = new RawMcpClient(new KernelBrowserPsr18Client($browser, $factory), $factory, $factory);
    }

    public function testFirstCallReturnsTheToolResult(): void
    {
        $result = $this->client->call(self::call('doc_submit_draft', ['title' => 'Quill 1.8', 'body' => 'Bug fixes.']), self::author());

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertFalse($result->isError);
        self::assertSame('in_review', \is_array($result->structuredContent) ? $result->structuredContent['state'] ?? null : null);
    }

    public function testInvisibleToolIsAnActionableToolError(): void
    {
        $result = $this->client->call(self::call('doc_approve'), self::author());

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
        $text = $result->content[0] ?? null;
        self::assertInstanceOf(TextContent::class, $text);
        self::assertStringContainsString('doc_submit_draft', \is_string($text->text) ? $text->text : '');
    }

    public function testRetryShapePassesTheHeaderValidatorAndReachesStateVerification(): void
    {
        $this->expectException(McpCallFailed::class);
        $this->expectExceptionMessage('MCP error -32602: The supplied requestState failed verification.');

        $this->client->call(self::call('doc_submit_draft', ['title' => 'Quill 1.8', 'body' => 'Bug fixes.']), self::author(), ['approval' => ['action' => 'accept', 'content' => []]], 'not-a-signed-state');
    }

    public function testUnknownTokenIsAnHttpError(): void
    {
        $this->expectException(McpCallFailed::class);
        $this->expectExceptionCode(401);

        $this->client->call(self::call('doc_submit_draft'), 'wrong-token');
    }

    /** @param array<string, mixed> $arguments */
    private static function call(string $tool, array $arguments = []): ToolCall
    {
        return new ToolCall('http://localhost/mcp/doc_review/d1', 'doc_review:d1', DocReviewCallers::AUTHOR, $tool, $arguments);
    }

    private static function author(): string
    {
        return DocReviewCallers::token(self::SECRET, DocReviewCallers::AUTHOR);
    }
}
