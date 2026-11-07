<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp;

use App\Tests\Unit\Lib\WorkflowMcp\Fixture\Document;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\DocumentWorkflow;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\Harness;
use Leash\WorkflowMcp\Event\CapabilityDenied;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class TransitionToolHandlerTest extends TestCase
{
    public function testGuardBlockedTransitionIsDeniedWithoutStateChange(): void
    {
        $harness = new Harness();
        $document = new Document('review');
        $document->locked = true;

        $result = $harness->transitionTools->handle(DocumentWorkflow::create(), $document, self::editor(), 'doc_approve', ['note' => 'x']);

        // The guard hides the tool, so the handler's visibility re-check denies it.
        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
        self::assertStringContainsString('Available now: doc_reject, doc_read_comments, doc_add_comment.', self::text($result));
        self::assertSame('review', $document->getStatus());
        self::assertCount(1, $harness->eventsOf(CapabilityDenied::class));
    }

    public function testPlaceToolNameIsNotATransition(): void
    {
        $result = (new Harness())->transitionTools->handle(DocumentWorkflow::create(), new Document('review'), self::editor(), 'doc_add_comment', []);

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
    }

    public function testTransitionWithoutInputPassesNullInputInTheContext(): void
    {
        $harness = new Harness();
        $document = new Document('review');

        $result = $harness->transitionTools->handle(DocumentWorkflow::create(), $document, self::editor(), 'doc_reject', []);

        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertFalse($result->isError);
        self::assertSame('draft', $document->getStatus());
        self::assertSame(['state' => 'draft', 'availableTools' => [], 'message' => 'Applied "doc_reject".'], $result->structuredContent);
    }

    private static function editor(): InMemoryUser
    {
        return new InMemoryUser('bob', null, ['ROLE_EDITOR']);
    }

    private static function text(CallToolResult $result): string
    {
        $content = $result->content[0] ?? null;

        return $content instanceof TextContent && \is_string($content->text) ? $content->text : '';
    }
}
