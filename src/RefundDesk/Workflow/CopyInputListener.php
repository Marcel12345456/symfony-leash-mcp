<?php

declare(strict_types=1);

namespace App\RefundDesk\Workflow;

use App\RefundDesk\Entity\RefundCase;
use App\RefundDesk\Mcp\Input\ProposeResolutionInput;
use App\RefundDesk\Mcp\Input\RejectInput;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/** Copies the tool input (`['input' => $dto]` in the transition context) into the case. */
final readonly class CopyInputListener
{
    /** @param TransitionEvent<object> $event */
    #[AsTransitionListener(workflow: 'refund_case', transition: 'propose_resolution')]
    public function onPropose(TransitionEvent $event): void
    {
        $case = $event->getSubject();
        $input = $event->getContext()['input'] ?? null;
        if ($case instanceof RefundCase && $input instanceof ProposeResolutionInput) {
            $case->propose($input->decision, $input->amountCents, $input->rationale);
        }
    }

    /** @param TransitionEvent<object> $event */
    #[AsTransitionListener(workflow: 'refund_case', transition: 'reject')]
    public function onReject(TransitionEvent $event): void
    {
        $case = $event->getSubject();
        $input = $event->getContext()['input'] ?? null;
        if ($case instanceof RefundCase && $input instanceof RejectInput) {
            $case->reject($input->customerReply);
        }
    }
}
