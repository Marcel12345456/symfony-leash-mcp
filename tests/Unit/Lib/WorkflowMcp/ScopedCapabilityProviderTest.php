<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp;

use App\Tests\Unit\Lib\WorkflowMcp\Fixture\ApproveInput;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\Document;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\DocumentWorkflow;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\OwnerRoleResolver;
use Leash\WorkflowMcp\Capability\VisibleTool;
use Leash\WorkflowMcp\PlaceTool\PlaceToolRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class ScopedCapabilityProviderTest extends TestCase
{
    /** @return iterable<string, array{string, string, list<string>}> */
    public static function everyPlaceAndRole(): iterable
    {
        yield 'draft × author' => ['draft', 'ROLE_AUTHOR', ['doc_submit', 'doc_archive']];
        yield 'draft × editor' => ['draft', 'ROLE_EDITOR', []];
        yield 'review × author' => ['review', 'ROLE_AUTHOR', ['doc_read_comments']];
        yield 'review × editor' => ['review', 'ROLE_EDITOR', ['doc_approve', 'doc_reject', 'doc_read_comments', 'doc_add_comment']];
        yield 'published × author' => ['published', 'ROLE_AUTHOR', []];
        yield 'published × editor' => ['published', 'ROLE_EDITOR', ['doc_archive']];
        yield 'archived × author' => ['archived', 'ROLE_AUTHOR', []];
        yield 'archived × editor' => ['archived', 'ROLE_EDITOR', []];
    }

    /** @param list<string> $expected */
    #[DataProvider('everyPlaceAndRole')]
    public function testVisibilityForEveryPlaceAndRole(string $place, string $role, array $expected): void
    {
        $tools = DocumentWorkflow::provider()->visibleTools(DocumentWorkflow::create(), new Document($place), new InMemoryUser('bob', null, [$role]));

        self::assertSame($expected, self::names($tools));
    }

    public function testTransitionToolCarriesMetadataAndPlaceToolCarriesAttribute(): void
    {
        $tools = DocumentWorkflow::provider()->visibleTools(DocumentWorkflow::create(), new Document('review'), new InMemoryUser('bob', null, ['ROLE_EDITOR']));
        $byName = array_column(array_map(static fn(VisibleTool $t): array => [$t->name, $t], $tools), 1, 0);

        self::assertSame(ApproveInput::class, $byName['doc_approve']->inputClass);
        self::assertFalse($byName['doc_approve']->readOnly);
        self::assertSame('approve', $byName['doc_approve']->transition?->getName());
        self::assertTrue($byName['doc_read_comments']->readOnly);
        self::assertFalse($byName['doc_add_comment']->readOnly);
        self::assertSame('Add a review comment.', $byName['doc_add_comment']->description);
    }

    public function testGuardHidesTransitionTool(): void
    {
        $document = new Document('review');
        $document->locked = true;

        $tools = DocumentWorkflow::provider()->visibleTools(DocumentWorkflow::create(), $document, new InMemoryUser('bob', null, ['ROLE_EDITOR']));

        self::assertSame(['doc_reject', 'doc_read_comments', 'doc_add_comment'], self::names($tools));
    }

    public function testSameTransitionNameFromDifferentPlacesUsesTheInstanceOfTheCurrentPlace(): void
    {
        $provider = DocumentWorkflow::provider();
        $workflow = DocumentWorkflow::create();

        $fromDraft = $provider->visibleTools($workflow, new Document('draft'), new InMemoryUser('a', null, ['ROLE_AUTHOR']));
        $fromPublished = $provider->visibleTools($workflow, new Document('published'), new InMemoryUser('e', null, ['ROLE_EDITOR']));

        self::assertSame(['draft'], $fromDraft[1]->transition?->getFroms());
        self::assertSame(['published'], $fromPublished[0]->transition?->getFroms());
    }

    public function testCustomRoleResolverFromWorkflowMetadata(): void
    {
        $workflow = DocumentWorkflow::create(['mcp_prefix' => 'doc', 'mcp_role_resolver' => OwnerRoleResolver::class]);
        $provider = DocumentWorkflow::provider();
        $draft = new Document('draft', owner: 'alice');

        // Security roles are ignored; ownership decides.
        self::assertSame(['doc_submit', 'doc_archive'], self::names($provider->visibleTools($workflow, $draft, new InMemoryUser('alice', null, ['ROLE_EDITOR']))));
        self::assertSame([], self::names($provider->visibleTools($workflow, $draft, new InMemoryUser('mallory', null, ['ROLE_AUTHOR']))));
    }

    public function testPrefixDefaultsToWorkflowName(): void
    {
        $tools = DocumentWorkflow::provider()->visibleTools(DocumentWorkflow::create([]), new Document('draft'), new InMemoryUser('a', null, ['ROLE_AUTHOR']));

        self::assertSame(['document_submit', 'document_archive'], self::names($tools));
    }

    public function testUnknownPlaceToolIsAConfigurationError(): void
    {
        $provider = new \Leash\WorkflowMcp\Capability\ScopedCapabilityProvider(new PlaceToolRegistry([]), new \Symfony\Component\DependencyInjection\ServiceLocator([]));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('doc_read_comments');

        $provider->visibleTools(DocumentWorkflow::create(), new Document('review'), new InMemoryUser('a', null, ['ROLE_AUTHOR']));
    }

    /**
     * @param list<VisibleTool> $tools
     *
     * @return list<string>
     */
    private static function names(array $tools): array
    {
        return array_map(static fn(VisibleTool $t): string => $t->name, $tools);
    }
}
