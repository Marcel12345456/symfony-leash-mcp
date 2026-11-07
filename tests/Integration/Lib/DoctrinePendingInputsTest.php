<?php

declare(strict_types=1);

namespace App\Tests\Integration\Lib;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Leash\HumanInput\Client\InputRequired;
use Leash\HumanInput\Client\ToolCall;
use Leash\HumanInput\Doctrine\DoctrinePendingInputs;
use Leash\HumanInput\Pending\PendingInput;
use Leash\HumanInput\Pending\PendingInputStatus;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrinePendingInputsTest extends KernelTestCase
{
    public function testStoresTheCallAsksAndAnswersLosslessly(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $schema = new SchemaTool($em);
        $schema->dropDatabase();
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());
        $store = new DoctrinePendingInputs($em);

        $asks = ['approval' => ['method' => 'elicitation/create', 'params' => ['message' => 'Refund?', 'requestedSchema' => ['type' => 'object']]]];
        $call = new ToolCall('http://localhost/mcp/refund_case/C2', 'refund_case:C2', 'agent:resolver', 'refund_issue', ['amountCents' => 18000]);
        $pending = new PendingInput($call, new InputRequired($asks, 'signed-state'), new \DateTimeImmutable('2026-11-07 12:00:00'));
        $store->save($pending);
        $pending->answer(['approval' => ['action' => 'accept', 'content' => ['approve' => true]]], new \DateTimeImmutable('2026-11-07 12:05:00'));
        $store->save($pending);
        $em->clear();

        $loaded = $store->get($pending->id);

        self::assertNotNull($loaded);
        self::assertEquals($call, $loaded->toolCall());
        self::assertSame($asks, $loaded->inputRequests);
        self::assertSame('signed-state', $loaded->requestState);
        self::assertSame(PendingInputStatus::Answered, $loaded->status);
        self::assertSame(['approval' => ['action' => 'accept', 'content' => ['approve' => true]]], $loaded->responses);
        self::assertSame([], $store->withStatus(PendingInputStatus::Pending));
        self::assertSame([$pending->id], array_map(static fn(PendingInput $p): string => $p->id, $store->withStatus(PendingInputStatus::Answered)));
    }
}
