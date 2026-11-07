<?php

declare(strict_types=1);

namespace App\Tests\Integration\Inbox;

use App\Inbox\EmailHumanInputNotifier;
use App\Inbox\InboxLinks;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Leash\HumanInput\Client\InputRequired;
use Leash\HumanInput\Client\ToolCall;
use Leash\HumanInput\Pending\PendingInput;
use Leash\HumanInput\Pending\PendingInputs;
use Leash\HumanInput\Pending\PendingInputStatus;
use Leash\HumanInput\Pending\ResumePendingInput;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** M2 acceptance: inbox login, signed expiring item links, submit → ResumePendingInput, email with the signed link. */
final class InboxTest extends WebTestCase
{
    private KernelBrowser $browser;
    private PendingInput $item;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $schema = new SchemaTool($em);
        $schema->dropDatabase();
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());

        $this->item = new PendingInput(
            new ToolCall('http://localhost/mcp/game/g1', 'game:g1', 'seat:5', 'ww_vote', []),
            new InputRequired(['vote' => ['method' => 'elicitation/create', 'params' => [
                'message' => 'Vote: who do you eliminate?',
                'requestedSchema' => ['type' => 'object', 'properties' => ['target' => ['type' => 'string', 'oneOf' => [
                    ['const' => '1', 'title' => 'Ada (seat 1)'],
                    ['const' => '2', 'title' => 'Ben (seat 2)'],
                ]]], 'required' => ['target']],
            ]]], 'signed-state'),
            new \DateTimeImmutable(),
        );
        $this->pending()->save($this->item);
    }

    public function testInboxRequiresLogin(): void
    {
        $this->browser->request('GET', '/inbox');

        self::assertResponseRedirects('/login');
    }

    public function testApproverLogsInAndSeesThePendingItem(): void
    {
        $this->browser->request('GET', '/login');
        $this->browser->submitForm('Log in', ['_username' => 'approver', '_password' => 'approver-test-password']);
        $this->browser->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '#' . $this->item->id . ' ww_vote on game:g1');
    }

    public function testWrongPasswordIsRejected(): void
    {
        $this->browser->request('GET', '/login');
        $this->browser->submitForm('Log in', ['_username' => 'approver', '_password' => 'nope']);

        self::assertResponseRedirects('/login');
    }

    public function testSignedLinkOpensTheItemAsAChoiceWithoutLogin(): void
    {
        $this->browser->request('GET', $this->links()->item($this->item->id));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Vote: who do you eliminate?');
        self::assertSelectorExists('input[type=radio][name="vote[target]"][value="2"]');
        self::assertSelectorTextContains('label[for="vote_target_1"]', 'Ben (seat 2)');
    }

    public function testExpiredLinkIsGone(): void
    {
        $signer = self::getContainer()->get(UriSigner::class);
        $this->browser->request('GET', $signer->sign($this->itemUrl(), new \DateTimeImmutable('-1 minute')));

        self::assertResponseStatusCodeSame(410);
    }

    public function testUnsignedOrTamperedLinkIsForbidden(): void
    {
        $this->browser->request('GET', $this->itemUrl());
        self::assertResponseStatusCodeSame(403);

        $this->browser->request('GET', str_replace($this->item->id, 'ffffffffffff', $this->links()->item($this->item->id)));
        self::assertResponseStatusCodeSame(403);
    }

    public function testSubmittingTheChoiceRecordsTheAnswerAndQueuesTheResume(): void
    {
        $this->browser->request('GET', $this->links()->item($this->item->id));
        $this->browser->submitForm('Send answer', ['vote[target]' => '2']);

        self::assertResponseIsSuccessful();
        $item = $this->pending()->get($this->item->id);
        self::assertSame(PendingInputStatus::Answered, $item?->status);
        self::assertSame(['vote' => ['action' => 'accept', 'content' => ['target' => '2']]], json_decode(json_encode($item->responses, \JSON_THROW_ON_ERROR), true));
        $transport = self::getContainer()->get('messenger.transport.async');
        \assert($transport instanceof InMemoryTransport);
        self::assertEquals([new ResumePendingInput($this->item->id)], array_map(static fn($e) => $e->getMessage(), $transport->getSent()));
    }

    public function testDeclineNeedsNoChoice(): void
    {
        $this->browser->request('GET', $this->links()->item($this->item->id));
        $this->browser->submitForm('Decline');

        self::assertResponseIsSuccessful();
        self::assertSame(['vote' => ['action' => 'decline']], $this->pending()->get($this->item->id)?->responses);
    }

    public function testEmailCarriesTheSignedLink(): void
    {
        $notifier = self::getContainer()->get(EmailHumanInputNotifier::class);
        $notifier->notify($this->item);

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertNotNull($email);
        self::assertEmailTextBodyContains($email, 'Vote: who do you eliminate?');
        self::assertEmailTextBodyContains($email, '/inbox/item/' . $this->item->id . '?_expiration=');
        self::assertEmailTextBodyContains($email, '_hash=');
    }

    private function itemUrl(): string
    {
        $urls = self::getContainer()->get(UrlGeneratorInterface::class);

        return $urls->generate('inbox_item', ['id' => $this->item->id], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function links(): InboxLinks
    {
        $links = self::getContainer()->get(InboxLinks::class);

        return $links;
    }

    private function pending(): PendingInputs
    {
        $pending = self::getContainer()->get(PendingInputs::class);

        return $pending;
    }
}
