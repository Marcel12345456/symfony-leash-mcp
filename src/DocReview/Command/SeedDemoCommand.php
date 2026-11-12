<?php

declare(strict_types=1);

namespace App\DocReview\Command;

use App\DocReview\Entity\DocEvent;
use App\DocReview\Entity\Document;
use Leash\HumanInput\Pending\PendingInput;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Doc Review demo documents d1–d3 (decisions/022, 036), all fictional, for the fictional open-source project Quill.
 * d1 is clean; d2's brief omits the upgrade notes the rubric requires, so a first draft likely fails review;
 * d3 is ordinary, the demo's editor declines it with a note.
 * Idempotent: a document whose id already exists is left alone (whatever its state), missing ones are created in
 * `drafting`. `--reset` starts over: it first removes d1–d3, their events and their pending human inputs.
 */
#[AsCommand('leash:demo:seed', 'Create the Doc Review demo documents d1–d3 (existing ones are left alone unless --reset)')]
final readonly class SeedDemoCommand
{
    /** Document id => brief. */
    public const array BRIEFS = [
        'd1' => "Blog post announcing Quill 1.8, a bug-fix release of Quill, the (fictional) open-source Markdown editor.\n"
            . 'Fixes: the cursor no longer jumps when pasting tables; exported PDFs keep their page margins; '
            . 'the spell checker stops flagging words inside code blocks. No new features, no breaking changes. '
            . 'Audience: existing users. Tone: friendly, short.',
        'd2' => "Release notes for Quill 2.0, the (fictional) open-source Markdown editor.\n"
            . 'Highlights: a new plugin API (plugins register commands and sidebar panels), a faster renderer for '
            . 'documents over 10,000 lines, and a dark theme. Node 18 is no longer supported; Quill 2.0 runs on '
            . 'Node 20 or later. Audience: plugin authors and self-hosting users.',
        'd3' => "Release notes for Quill 2.1, the (fictional) open-source Markdown editor.\n"
            . 'Changes: footnotes render in the preview, the plugin API gains an onSave hook, and the command palette '
            . 'remembers recent commands. Fixes: two crashes when opening very large files. Audience: all users.',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Remove d1–d3, their events and pending inputs first')]
        bool $reset = false,
    ): int {
        if ($reset) {
            $ids = array_keys(self::BRIEFS);
            $this->entityManager->createQuery('DELETE FROM ' . DocEvent::class . ' e WHERE e.documentId IN (:ids)')->setParameter('ids', $ids)->execute();
            $this->entityManager->createQuery('DELETE FROM ' . PendingInput::class . ' p WHERE p.subjectRef IN (:refs)')
                ->setParameter('refs', array_map(static fn(string $id): string => 'doc_review:' . $id, $ids))->execute();
            $this->entityManager->createQuery('DELETE FROM ' . Document::class . ' d WHERE d.id IN (:ids)')->setParameter('ids', $ids)->execute();
        }
        $created = 0;
        foreach (self::BRIEFS as $id => $brief) {
            if (null === $this->entityManager->find(Document::class, $id)) {
                $this->entityManager->persist(new Document($id, $brief));
                ++$created;
            }
        }
        $this->entityManager->flush();

        $io->success(\sprintf('Seeded %d new document(s); %d already existed.', $created, \count(self::BRIEFS) - $created));

        return Command::SUCCESS;
    }
}
