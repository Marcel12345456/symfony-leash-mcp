<?php

declare(strict_types=1);

namespace App\Tests\Unit\RefundDesk;

use App\RefundDesk\Mcp\Input\SearchPolicyInput;
use App\RefundDesk\Mcp\Tool\SearchPolicyTool;
use App\Tests\Integration\RefundDesk\RefundCaseFactory;
use PHPUnit\Framework\TestCase;

final class SearchPolicyToolTest extends TestCase
{
    public function testKeywordSearchRanksTheMatchingSectionFirst(): void
    {
        $result = (new SearchPolicyTool(__DIR__ . '/../../../data/policy'))(RefundCaseFactory::create(), new SearchPolicyInput('IBAN payout account'));

        self::assertIsArray($result['results']);
        self::assertNotEmpty($result['results']);
        self::assertIsArray($result['results'][0]);
        self::assertSame('payments-and-approval.md', $result['results'][0]['source']);
        self::assertIsString($result['results'][0]['text']);
        self::assertStringStartsWith('## Payout method', $result['results'][0]['text']);
    }
}
