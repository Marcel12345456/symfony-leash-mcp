<?php

declare(strict_types=1);

namespace App\RefundDesk\Security;

use App\RefundDesk\Entity\RefundCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * REFUND_ISSUE: only a resolver, and never more than the order amount. Server-side backstop against a model
 * that proposes an inflated refund (seed case C3).
 *
 * @extends Voter<string, RefundCase>
 */
final class RefundIssueVoter extends Voter
{
    public const string ATTRIBUTE = 'REFUND_ISSUE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ATTRIBUTE === $attribute && $subject instanceof RefundCase;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!\in_array('ROLE_RESOLVER', $token->getRoleNames(), true)) {
            $vote?->addReason('Only a resolver may issue refunds.');

            return false;
        }

        $amount = $subject->getProposedAmountCents();
        $order = $subject->getOrder();
        if (null === $amount || null === $order || $amount > $order->getAmountCents()) {
            $vote?->addReason('The proposed amount must not exceed the order amount.');

            return false;
        }

        return true;
    }
}
