<?php

declare(strict_types=1);

namespace App\Tests\Integration\DocReview;

use App\Agent\DocReview\DocWorkRunner;
use App\Agent\ModelChoice;
use App\DocReview\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Leash\HumanInput\Pending\PendingInputAnswers;
use Leash\HumanInput\Pending\PendingInputs;
use Leash\HumanInput\Pending\PendingInputStatus;
use Leash\HumanInput\Pending\ResumePendingInput;
use Leash\HumanInput\Pending\ResumePendingInputHandler;
use Mcp\Schema\Enum\ElicitAction;
use Mcp\Schema\Result\ElicitResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * M6 done-signal: scripted author and reviewer agents (no LLM) work a document over MCP. The workflow picks who
 * acts; publishing defers to the editor (pending input, the run stops); the editor declines with a note in the
 * inbox; the resumed call opens `doc_send_back`, and the author's next run sees the editor's note.
 */
final class DocReviewAgentsTest extends KernelTestCase
{
    /** @var array<string, list<ResultInterface|string>> */
    private array $script = [];

    /** @var array<string, list<string>> role => request payloads */
    private array $seen = ['author' => [], 'reviewer' => []];

    protected function setUp(): void
    {
        self::bootKernel();
        $em = $this->service(EntityManagerInterface::class);
        $schema = new SchemaTool($em);
        $schema->dropDatabase();
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());
        $em->persist(new Document('d1', 'Release notes for Quill 2.0 (fictional): new plugin API, Node 18 dropped.'));
        $em->flush();
    }

    public function testAgentsWorkUntilTheEditorThenTheDeclineNoteReachesTheAuthor(): void
    {
        $this->script = [
            'author' => [self::call('doc_submit_draft', ['title' => 'Quill 2.0', 'body' => 'New plugin API.']), 'Submitted.',
                self::call('doc_submit_draft', ['title' => 'Quill 2.0', 'body' => "New plugin API.\n\n## Upgrade notes\nNode 18 is no longer supported."]), 'Revised.'],
            'reviewer' => [self::call('doc_approve', []), 'Approved.', self::call('doc_publish', ['headline' => 'Quill 2.0 is out']), 'The editor was asked.',
                self::call('doc_send_back', []), 'Sent back.'],
        ];

        $first = $this->runner()->work('d1', $this->model());

        self::assertSame(['agent:author: drafting → in_review', 'agent:reviewer: in_review → approved', 'agent:reviewer: approved → approved'], $first);
        $pending = $this->service(PendingInputs::class)->withStatus(PendingInputStatus::Pending);
        self::assertCount(1, $pending);
        self::assertSame('doc_review:d1', $pending[0]->subjectRef);
        self::assertSame([], $this->runner()->work('d1', $this->model()), 'paused while the editor decides');

        $this->service(PendingInputAnswers::class)->answer($pending[0]->id, ['approval' => new ElicitResult(ElicitAction::Accept, ['approve' => false, 'note' => 'Add upgrade notes for Node 18 users.'])]);
        ($this->service(ResumePendingInputHandler::class))(new ResumePendingInput($pending[0]->id));
        $second = $this->runner()->work('d1', $this->model());

        self::assertSame(['agent:reviewer: approved → drafting', 'agent:author: drafting → in_review'], \array_slice($second, 0, 2));
        self::assertStringContainsString('Add upgrade notes for Node 18 users.', $this->seen['author'][2] ?? '', "the author's second run sees the editor's note");
    }

    private function model(): ModelChoice
    {
        return new ModelChoice(MockPlatformFactory::createPlatform(function (mixed $model, mixed $input): ResultInterface|string {
            $payload = (string) json_encode($input);
            $role = str_contains($payload, 'You are the author') ? 'author' : 'reviewer';
            $this->seen[$role][] = $payload;

            return array_shift($this->script[$role]) ?? 'Nothing to do.';
        }), 'scripted');
    }

    /** @param array<string, mixed> $arguments */
    private static function call(string $tool, array $arguments): ToolCallResult
    {
        return new ToolCallResult([new ToolCall('c1', $tool, $arguments)]);
    }

    private function runner(): DocWorkRunner
    {
        return $this->service(DocWorkRunner::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }
}
