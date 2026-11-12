<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\DocReview\Security\DocReviewCallers;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Bearer tokens on /mcp/ (derived from the test env's fixed secrets in .env.test). */
final class McpFirewallTest extends WebTestCase
{
    private const string SECRET = '$ecretf0rt3st';

    public function testInvalidTokenIsRejected(): void
    {
        $client = self::createClient();
        $client->request('POST', '/mcp/doc_review/d1', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer wrong'], content: '{}');

        self::assertResponseStatusCodeSame(401);
        self::assertStringStartsWith('Bearer error="invalid_token"', (string) $client->getResponse()->headers->get('WWW-Authenticate'));
    }

    public function testValidTokenReachesTheEndpoint(): void
    {
        $client = self::createClient();
        $client->request('POST', '/mcp/no_such_workflow/C1', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . DocReviewCallers::token(self::SECRET, DocReviewCallers::REVIEWER)], content: '{}');

        self::assertResponseStatusCodeSame(404);
        self::assertStringContainsString('Unknown workflow', (string) $client->getResponse()->getContent());
    }
}
