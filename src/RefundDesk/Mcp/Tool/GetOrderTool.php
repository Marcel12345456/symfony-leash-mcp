<?php

declare(strict_types=1);

namespace App\RefundDesk\Mcp\Tool;

use Symfony\Component\Security\Core\User\UserInterface;
use App\RefundDesk\Entity\RefundCase;
use Leash\WorkflowMcp\PlaceTool\AsPlaceTool;
use Leash\WorkflowMcp\PlaceTool\PlaceToolInterface;
use Psr\Clock\ClockInterface;

#[AsPlaceTool(workflow: 'refund_case', name: 'refund_get_order', description: 'Get the order of this case: item, amount in cents, delivery date, days since delivery, and the customer message.')]
final readonly class GetOrderTool implements PlaceToolInterface
{
    public function __construct(private ClockInterface $clock) {}

    public function __invoke(object $subject, ?object $input, UserInterface $caller): array
    {
        \assert($subject instanceof RefundCase);
        $order = $subject->getOrder();

        return [
            'caseId' => $subject->getId(),
            'customerMessage' => $subject->getCustomerMessage(),
            'order' => null === $order ? null : [
                'id' => $order->getId(),
                'item' => $order->getItem(),
                'amountCents' => $order->getAmountCents(),
                'deliveredAt' => $order->getDeliveredAt()->format('Y-m-d'),
                'daysSinceDelivery' => $order->getDeliveredAt()->diff($this->clock->now())->days,
            ],
            'note' => null === $order ? 'No order matches this case.' : null,
        ];
    }
}
