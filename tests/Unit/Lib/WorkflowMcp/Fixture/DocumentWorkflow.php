<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use Leash\WorkflowMcp\Capability\ScopedCapabilityProvider;
use Leash\WorkflowMcp\PlaceTool\PlaceToolRegistry;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;

/**
 * Synthetic workflow: draft → review → published, archive from draft and from published (same name, two instances).
 * `approve` is guarded: a locked document cannot be approved; with `$approvalOnApprove` it also needs a human (MRTR).
 */
final class DocumentWorkflow
{
    /** @param array<string, mixed> $workflowMetadata */
    public static function create(array $workflowMetadata = ['mcp_prefix' => 'doc'], bool $approvalOnApprove = false): StateMachine
    {
        $transitions = [
            'submit' => new Transition('submit', 'draft', 'review'),
            'approve' => new Transition('approve', 'review', 'published'),
            'reject' => new Transition('reject', 'review', 'draft'),
            'archive_draft' => new Transition('archive', 'draft', 'archived'),
            'archive_published' => new Transition('archive', 'published', 'archived'),
        ];

        /** @var \SplObjectStorage<Transition, array<mixed>> $transitionMetadata */
        $transitionMetadata = new \SplObjectStorage();
        $transitionMetadata[$transitions['submit']] = ['mcp_roles' => ['ROLE_AUTHOR'], 'mcp_description' => 'Submit for review.'];
        $transitionMetadata[$transitions['approve']] = ['mcp_roles' => ['ROLE_EDITOR'], 'mcp_input' => ApproveInput::class]
            + ($approvalOnApprove ? ['mcp_approval' => 'doc_approval'] : []);
        $transitionMetadata[$transitions['reject']] = ['mcp_roles' => ['ROLE_EDITOR']];
        $transitionMetadata[$transitions['archive_draft']] = ['mcp_roles' => ['ROLE_AUTHOR']];
        $transitionMetadata[$transitions['archive_published']] = ['mcp_roles' => ['ROLE_EDITOR']];

        $definition = new Definition(
            ['draft', 'review', 'published', 'archived'],
            array_values($transitions),
            'draft',
            new InMemoryMetadataStore(
                $workflowMetadata,
                ['review' => ['mcp_tools' => [
                    'doc_read_comments' => ['ROLE_AUTHOR', 'ROLE_EDITOR'],
                    'doc_add_comment' => ['ROLE_EDITOR'],
                ]]],
                $transitionMetadata,
            ),
        );

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('workflow.document.guard.approve', static function (GuardEvent $event): void {
            $subject = $event->getSubject();
            if ($subject instanceof Document && $subject->locked) {
                $event->setBlocked(true, 'Document is locked.');
            }
        });

        return new StateMachine($definition, new MethodMarkingStore(true, 'status'), $dispatcher, 'document');
    }

    public static function provider(): ScopedCapabilityProvider
    {
        return new ScopedCapabilityProvider(
            new PlaceToolRegistry([new ReadCommentsTool(), new AddCommentTool()]),
            new ServiceLocator([OwnerRoleResolver::class => static fn(): OwnerRoleResolver => new OwnerRoleResolver()]),
        );
    }
}
