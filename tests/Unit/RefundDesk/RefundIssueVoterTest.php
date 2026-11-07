<?php

declare(strict_types=1);

namespace App\Tests\Unit\RefundDesk;

use App\RefundDesk\Entity\Decision;
use App\RefundDesk\Security\RefundIssueVoter;
use App\Tests\Integration\RefundDesk\RefundCaseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class RefundIssueVoterTest extends TestCase
{
    /** @return iterable<string, array{string, int, int}> */
    public static function votes(): iterable
    {
        yield 'resolver, amount within order' => ['ROLE_RESOLVER', 1200, VoterInterface::ACCESS_GRANTED];
        yield 'resolver, amount over order (C3)' => ['ROLE_RESOLVER', 95000, VoterInterface::ACCESS_DENIED];
        yield 'investigator' => ['ROLE_INVESTIGATOR', 1200, VoterInterface::ACCESS_DENIED];
    }

    #[DataProvider('votes')]
    public function testVote(string $role, int $proposedCents, int $expected): void
    {
        $token = new UsernamePasswordToken(new InMemoryUser('agent', null, [$role]), 'main', [$role]);
        $case = RefundCaseFactory::create('proposed', Decision::Refund, $proposedCents);

        self::assertSame($expected, (new RefundIssueVoter())->vote($token, $case, [RefundIssueVoter::ATTRIBUTE]));
    }
}
