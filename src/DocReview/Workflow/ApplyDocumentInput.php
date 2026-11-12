<?php

declare(strict_types=1);

namespace App\DocReview\Workflow;

use App\DocReview\Entity\Document;
use App\DocReview\Mcp\Input\FeedbackInput;
use App\DocReview\Mcp\Input\PublishInput;
use App\DocReview\Mcp\Input\SubmitDraftInput;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/** Writes the tool input (`['input' => $dto]` in the transition context) into the document. */
final readonly class ApplyDocumentInput
{
    /** @param TransitionEvent<object> $event */
    #[AsTransitionListener(workflow: 'doc_review', transition: 'submit_draft')]
    public function onSubmit(TransitionEvent $event): void
    {
        [$document, $input] = [$event->getSubject(), $event->getContext()['input'] ?? null];
        if ($document instanceof Document && $input instanceof SubmitDraftInput) {
            $document->draft($input->title, $input->body);
        }
    }

    /** @param TransitionEvent<object> $event */
    #[AsTransitionListener(workflow: 'doc_review', transition: 'request_changes')]
    public function onRequestChanges(TransitionEvent $event): void
    {
        [$document, $input] = [$event->getSubject(), $event->getContext()['input'] ?? null];
        if ($document instanceof Document && $input instanceof FeedbackInput) {
            $document->addFeedback('reviewer', $input->feedback);
        }
    }

    /** @param TransitionEvent<object> $event */
    #[AsTransitionListener(workflow: 'doc_review', transition: 'publish')]
    public function onPublish(TransitionEvent $event): void
    {
        [$document, $input] = [$event->getSubject(), $event->getContext()['input'] ?? null];
        if ($document instanceof Document && $input instanceof PublishInput) {
            $document->publish($input->headline);
        }
    }

    /** @param TransitionEvent<object> $event */
    #[AsTransitionListener(workflow: 'doc_review', transition: 'send_back')]
    public function onSendBack(TransitionEvent $event): void
    {
        $document = $event->getSubject();
        if ($document instanceof Document) {
            $document->sendBack();
        }
    }
}
