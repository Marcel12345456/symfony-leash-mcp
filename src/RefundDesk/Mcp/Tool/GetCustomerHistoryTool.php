<?php

declare(strict_types=1);

namespace App\RefundDesk\Mcp\Tool;

use App\RefundDesk\Entity\Refund;
use App\RefundDesk\Entity\RefundCase;
use Doctrine\ORM\EntityManagerInterface;
use Leash\WorkflowMcp\PlaceTool\AsPlaceTool;
use Leash\WorkflowMcp\PlaceTool\PlaceToolInterface;

#[AsPlaceTool(workflow: 'refund_case', name: 'refund_get_customer_history', description: "Get the customer's other cases and earlier refunds. Use it to spot duplicate claims.")]
final readonly class GetCustomerHistoryTool implements PlaceToolInterface
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function __invoke(object $subject, ?object $input): array
    {
        \assert($subject instanceof RefundCase);
        $customer = $subject->getCustomer();

        $cases = $this->entityManager->getRepository(RefundCase::class)->findBy(['customer' => $customer], ['openedAt' => 'ASC']);
        $refunds = $this->entityManager->createQueryBuilder()
            ->select('r')->from(Refund::class, 'r')->join('r.case', 'c')
            ->where('c.customer = :customer')->setParameter('customer', $customer)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()->getResult();

        return [
            'customer' => ['id' => $customer->getId(), 'name' => $customer->getName()],
            'otherCases' => array_values(array_map(static fn(RefundCase $c): array => [
                'id' => $c->getId(),
                'orderId' => $c->getOrder()?->getId(),
                'status' => $c->getStatus(),
                'openedAt' => $c->getOpenedAt()->format('Y-m-d'),
            ], array_filter($cases, static fn(RefundCase $c): bool => $c->getId() !== $subject->getId()))),
            'refunds' => array_map(static fn(Refund $r): array => [
                'caseId' => $r->getCase()->getId(),
                'orderId' => $r->getCase()->getOrder()?->getId(),
                'amountCents' => $r->getAmountCents(),
                'createdAt' => $r->getCreatedAt()->format('Y-m-d'),
            ], \is_array($refunds) ? array_values(array_filter($refunds, static fn(mixed $r): bool => $r instanceof Refund)) : []),
        ];
    }
}
