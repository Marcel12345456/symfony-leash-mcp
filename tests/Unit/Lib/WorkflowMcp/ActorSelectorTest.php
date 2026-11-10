<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp;

use App\Tests\Unit\Lib\WorkflowMcp\Fixture\Document;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\DocumentWorkflow;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\OwnerRoleResolver;
use Leash\WorkflowMcp\Capability\ActorSelector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

final class ActorSelectorTest extends TestCase
{
    /** @return iterable<string, array{string, bool, list<string>}> */
    public static function everyPlace(): iterable
    {
        yield 'draft' => ['draft', false, ['ROLE_AUTHOR']];
        // The author sees only the read-only doc_read_comments in review, so it is not an actor.
        yield 'review' => ['review', false, ['ROLE_EDITOR']];
        yield 'published' => ['published', false, ['ROLE_EDITOR']];
        yield 'archived (terminal)' => ['archived', false, []];
        // Guard blocks approve, but reject and doc_add_comment still make the editor the actor.
        yield 'review, locked' => ['review', true, ['ROLE_EDITOR']];
    }

    /** @param list<string> $expected */
    #[DataProvider('everyPlace')]
    public function testSelectsRolesWithAnActingTool(string $place, bool $locked, array $expected): void
    {
        $document = new Document($place);
        $document->locked = $locked;

        self::assertSame($expected, (new ActorSelector(DocumentWorkflow::provider()))->select(DocumentWorkflow::create(), $document));
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function perCaller(): iterable
    {
        // OwnerRoleResolver: the owner "alice" is ROLE_AUTHOR, everyone else ROLE_EDITOR.
        yield 'draft: only the owner acts' => ['draft', ['alice']];
        yield 'review: every non-owner acts' => ['review', ['bob', 'carol']];
        yield 'archived: nobody' => ['archived', []];
    }

    /** @param list<string> $expected */
    #[DataProvider('perCaller')]
    public function testPicksTheCallersWithAnActingTool(string $place, array $expected): void
    {
        $document = new Document($place);
        $document->owner = 'alice';
        $callers = [new InMemoryUser('alice', null), new InMemoryUser('bob', null), new InMemoryUser('carol', null)];

        $actors = (new ActorSelector(DocumentWorkflow::provider()))
            ->actorsAmong(DocumentWorkflow::create(['mcp_prefix' => 'doc', 'mcp_role_resolver' => OwnerRoleResolver::class]), $document, $callers);

        self::assertSame($expected, array_map(static fn(UserInterface $u): string => $u->getUserIdentifier(), $actors));
    }
}
