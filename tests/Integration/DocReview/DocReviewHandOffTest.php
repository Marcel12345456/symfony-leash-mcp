<?php

declare(strict_types=1);

namespace App\Tests\Integration\DocReview;

use App\DocReview\Entity\Document;
use App\DocReview\Security\DocReviewCallers;
use Leash\WorkflowMcp\Capability\ActorSelector;
use Leash\WorkflowMcp\Capability\ScopedCapabilityProvider;
use Leash\WorkflowMcp\Capability\VisibleResource;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * PLAN §7 Doc Review, on the real `doc_review` workflow: the workflow hands the document from author to reviewer;
 * the editor is the human behind `doc_publish` (MRTR), and a declined document goes back to the author (G3).
 * The rubric is the reviewer's, and only while reviewing.
 */
final class DocReviewHandOffTest extends KernelTestCase
{
    /** @return iterable<string, array{string, bool, list<string>}> place, editor declined, expected actors */
    public static function handOff(): iterable
    {
        yield 'drafting: the author writes' => ['drafting', false, [DocReviewCallers::AUTHOR]];
        yield 'in_review: the reviewer reviews' => ['in_review', false, [DocReviewCallers::REVIEWER]];
        yield 'approved: the reviewer publishes, which asks the editor' => ['approved', false, [DocReviewCallers::REVIEWER]];
        yield 'approved, editor declined: the reviewer sends it back' => ['approved', true, [DocReviewCallers::REVIEWER]];
        yield 'published: nobody' => ['published', false, []];
    }

    /** @param list<string> $expected */
    #[DataProvider('handOff')]
    public function testTheWorkflowPicksTheNextAgent(string $place, bool $declined, array $expected): void
    {
        $document = self::document($place, $declined);
        $actors = self::service(ActorSelector::class)->actorsAmong(self::workflow(), $document, self::callers());

        self::assertSame($expected, array_map(static fn(InMemoryUser $u): string => $u->getUserIdentifier(), $actors));
    }

    public function testDeclinedDocumentGoesBackToTheAuthor(): void
    {
        $document = self::document('approved', true);
        $workflow = self::workflow();
        self::assertTrue($workflow->can($document, 'send_back'), 'the editor note opens send_back');

        $workflow->apply($document, 'send_back');

        self::assertSame('drafting', $document->getStatus());
        self::assertSame([DocReviewCallers::AUTHOR], array_map(
            static fn(InMemoryUser $u): string => $u->getUserIdentifier(),
            self::service(ActorSelector::class)->actorsAmong($workflow, $document, self::callers()),
        ));
    }

    public function testRubricIsVisibleOnlyToTheReviewerInReview(): void
    {
        $seen = [];
        foreach (['drafting', 'in_review', 'approved', 'published'] as $place) {
            foreach (self::callers() as $caller) {
                $names = array_map(static fn(VisibleResource $r): string => $r->name, self::service(ScopedCapabilityProvider::class)->visibleResources(self::workflow(), self::document($place, false), $caller));
                if (\in_array('rubric', $names, true)) {
                    $seen[] = $place . ' ' . $caller->getUserIdentifier();
                }
            }
        }

        self::assertSame(['in_review ' . DocReviewCallers::REVIEWER], $seen);
    }

    /** @return list<InMemoryUser> the two agents, as `DocWorkRunner` asks for them */
    private static function callers(): array
    {
        return [
            new InMemoryUser(DocReviewCallers::AUTHOR, null, ['ROLE_AUTHOR']),
            new InMemoryUser(DocReviewCallers::REVIEWER, null, ['ROLE_REVIEWER']),
        ];
    }

    private static function document(string $place, bool $declined): Document
    {
        $document = new Document('d1', 'A brief.');
        $document->setStatus($place);
        if ($declined) {
            $document->noteFromEditor('Not yet, see the upgrade notes.');
        }

        return $document;
    }

    private static function workflow(): WorkflowInterface
    {
        $workflow = self::getContainer()->get('state_machine.doc_review');
        self::assertInstanceOf(WorkflowInterface::class, $workflow);

        return $workflow;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private static function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }
}
