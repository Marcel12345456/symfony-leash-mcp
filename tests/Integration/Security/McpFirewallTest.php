<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Bearer tokens on /mcp/ (fixed test values in .env.test). */
final class McpFirewallTest extends WebTestCase
{
    public function testInvalidTokenIsRejected(): void
    {
        $client = self::createClient();
        $client->request('POST', '/mcp/refund_case/C1', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer wrong'], content: '{}');

        self::assertResponseStatusCodeSame(401);
        self::assertStringStartsWith('Bearer error="invalid_token"', (string) $client->getResponse()->headers->get('WWW-Authenticate'));
    }

    public function testValidTokenReachesTheEndpoint(): void
    {
        $client = self::createClient();
        $client->request('POST', '/mcp/no_such_workflow/C1', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer test-resolver-token'], content: '{}');

        self::assertResponseStatusCodeSame(404);
        self::assertStringContainsString('Unknown workflow', (string) $client->getResponse()->getContent());
    }
}
