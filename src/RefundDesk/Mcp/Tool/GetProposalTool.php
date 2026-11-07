<?php

declare(strict_types=1);

namespace App\RefundDesk\Mcp\Tool;

use App\RefundDesk\Entity\RefundCase;
use Leash\WorkflowMcp\PlaceTool\AsPlaceTool;
use Leash\WorkflowMcp\PlaceTool\PlaceToolInterface;

#[AsPlaceTool(workflow: 'refund_case', name: 'refund_get_proposal', description: "Get the investigator's proposed resolution: decision, amount in cents, rationale, and the order amount.")]
final readonly class GetProposalTool implements PlaceToolInterface
{
    public function __invoke(object $subject, ?object $input): array
    {
        \assert($subject instanceof RefundCase);

        return [
            'caseId' => $subject->getId(),
            'decision' => $subject->getDecision()?->value,
            'amountCents' => $subject->getProposedAmountCents(),
            'rationale' => $subject->getRationale(),
            'orderAmountCents' => $subject->getOrder()?->getAmountCents(),
            'customerMessage' => $subject->getCustomerMessage(),
        ];
    }
}
