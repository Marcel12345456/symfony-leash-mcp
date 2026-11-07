<?php

declare(strict_types=1);

namespace App\Tests\Integration\Lib;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Endpoint guards that need no workflow; the full MCP round-trip is RefundMcpEndpointTest (M1 step 4). */
final class WorkflowMcpControllerTest extends WebTestCase
{
    public function testWithoutCallerAsksForABearerToken(): void
    {
        $client = self::createClient();
        $client->request('POST', '/mcp/any_workflow/1', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer');
    }
}
