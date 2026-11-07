<?php

declare(strict_types=1);

namespace App\RefundDesk\Workflow;

use App\RefundDesk\Entity\RefundCase;
use App\RefundDesk\Payment\FakePaymentProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/** `issue` pays the proposed amount through the fake provider (M1: no amount input, no approval yet). */
final readonly class IssueRefundListener
{
    public function __construct(
        private FakePaymentProvider $payments,
        private EntityManagerInterface $entityManager,
    ) {}

    /** @param TransitionEvent<object> $event */
    #[AsTransitionListener(workflow: 'refund_case', transition: 'issue')]
    public function __invoke(TransitionEvent $event): void
    {
        $case = $event->getSubject();
        $amount = $case instanceof RefundCase ? $case->getProposedAmountCents() : null;
        if ($case instanceof RefundCase && null !== $amount) {
            $this->entityManager->persist($this->payments->refund($case, $amount, $amount));
        }
    }
}
