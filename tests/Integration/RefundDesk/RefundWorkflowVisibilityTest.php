<?php

declare(strict_types=1);

namespace App\Tests\Integration\RefundDesk;

use App\RefundDesk\Entity\Decision;
use App\RefundDesk\Entity\RefundCase;
use App\RefundDesk\Mcp\Input\ProposeResolutionInput;
use Leash\WorkflowMcp\Capability\ActorSelector;
use Leash\WorkflowMcp\Capability\ScopedCapabilityProvider;
use Leash\WorkflowMcp\Capability\VisibleTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Workflow\WorkflowInterface;

/** The refund workflow YAML through the library: every place × role, guards and voter, actor per state. */
final class RefundWorkflowVisibilityTest extends KernelTestCase
{
    /** @return iterable<string, array{RefundCase, string, list<string>}> */
    public static function everyPlaceAndRole(): iterable
    {
        $refundProposal = static fn(int $cents = 1200): RefundCase => RefundCaseFactory::create('proposed', Decision::Refund, $cents);

        yield 'received × investigator' => [RefundCaseFactory::create('received'), 'ROLE_INVESTIGATOR', ['refund_start_investigation']];
        yield 'received × resolver' => [RefundCaseFactory::create('received'), 'ROLE_RESOLVER', []];
        yield 'investigating × investigator' => [RefundCaseFactory::create('investigating'), 'ROLE_INVESTIGATOR', ['refund_propose_resolution', 'refund_get_order', 'refund_get_customer_history', 'refund_search_policy']];
        yield 'investigating × resolver' => [RefundCaseFactory::create('investigating'), 'ROLE_RESOLVER', ['refund_search_policy']];
        yield 'proposed (refund) × investigator' => [$refundProposal(), 'ROLE_INVESTIGATOR', []];
        yield 'proposed (refund) × resolver' => [$refundProposal(), 'ROLE_RESOLVER', ['refund_issue', 'refund_revise', 'refund_get_proposal', 'refund_search_policy']];
        yield 'proposed (refund over order amount) × resolver: voter hides issue' => [$refundProposal(95000), 'ROLE_RESOLVER', ['refund_revise', 'refund_get_proposal', 'refund_search_policy']];
        yield 'proposed (reject) × resolver' => [RefundCaseFactory::create('proposed', Decision::Reject), 'ROLE_RESOLVER', ['refund_reject', 'refund_revise', 'refund_get_proposal', 'refund_search_policy']];
        yield 'refunded × investigator' => [RefundCaseFactory::create('refunded'), 'ROLE_INVESTIGATOR', []];
        yield 'refunded × resolver' => [RefundCaseFactory::create('refunded'), 'ROLE_RESOLVER', ['refund_close']];
        yield 'rejected × resolver' => [RefundCaseFactory::create('rejected'), 'ROLE_RESOLVER', ['refund_close']];
        yield 'closed × investigator' => [RefundCaseFactory::create('closed'), 'ROLE_INVESTIGATOR', []];
        yield 'closed × resolver' => [RefundCaseFactory::create('closed'), 'ROLE_RESOLVER', []];
    }

    /** @param list<string> $expected */
    #[DataProvider('everyPlaceAndRole')]
    public function testVisibilityForEveryPlaceAndRole(RefundCase $case, string $role, array $expected): void
    {
        $caller = $this->loginAs($role);

        $tools = self::service(ScopedCapabilityProvider::class)->visibleTools(self::workflow(), $case, $caller);

        self::assertSame($expected, array_map(static fn(VisibleTool $t): string => $t->name, $tools));
    }

    /** @return iterable<string, array{RefundCase, list<string>}> */
    public static function everyState(): iterable
    {
        yield 'received' => [RefundCaseFactory::create('received'), ['ROLE_INVESTIGATOR']];
        // The resolver's only tool here is the read-only refund_search_policy (G3).
        yield 'investigating' => [RefundCaseFactory::create('investigating'), ['ROLE_INVESTIGATOR']];
        // Token-less: the voter hides issue, revise still makes the resolver the actor (decisions/012).
        yield 'proposed (refund)' => [RefundCaseFactory::create('proposed', Decision::Refund, 1200), ['ROLE_RESOLVER']];
        yield 'proposed (reject)' => [RefundCaseFactory::create('proposed', Decision::Reject), ['ROLE_RESOLVER']];
        yield 'refunded' => [RefundCaseFactory::create('refunded'), ['ROLE_RESOLVER']];
        yield 'rejected' => [RefundCaseFactory::create('rejected'), ['ROLE_RESOLVER']];
        yield 'closed' => [RefundCaseFactory::create('closed'), []];
    }

    /** @param list<string> $expected */
    #[DataProvider('everyState')]
    public function testActorSelectorPicksTheRoleForEveryState(RefundCase $case, array $expected): void
    {
        self::assertSame($expected, (new ActorSelector(self::service(ScopedCapabilityProvider::class)))->select(self::workflow(), $case));
    }

    public function testProposeResolutionCopiesTheInputIntoTheCase(): void
    {
        $case = RefundCaseFactory::create('investigating');

        self::workflow()->apply($case, 'propose_resolution', ['input' => new ProposeResolutionInput(Decision::Refund, 1200, 'Damaged on arrival.')]);

        self::assertSame('proposed', $case->getStatus());
        self::assertTrue($case->isRefundDecision());
        self::assertSame(1200, $case->getProposedAmountCents());
        self::assertSame('Damaged on arrival.', $case->getRationale());
    }

    private function loginAs(string $role): InMemoryUser
    {
        $user = new InMemoryUser('agent:test', null, [$role]);
        self::service(TokenStorageInterface::class, 'security.token_storage')->setToken(new UsernamePasswordToken($user, 'main', [$role]));

        return $user;
    }

    private static function workflow(): WorkflowInterface
    {
        return self::service(WorkflowInterface::class, 'state_machine.refund_case');
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function service(string $class, ?string $id = null): object
    {
        $service = self::getContainer()->get($id ?? $class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
