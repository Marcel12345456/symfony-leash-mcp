<?php

declare(strict_types=1);

namespace App\DocReview\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Subject of the `doc_review` state machine (PLAN §3.6, decisions/036): a brief, the current draft, the reviewer's
 * and editor's feedback, and the headline it was published under. Fictional content only.
 */
#[ORM\Entity]
class Document
{
    #[ORM\Column(length: 32)]
    private string $status = 'drafting';

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $headline = null;

    /** @var list<array{from: string, text: string}> newest last */
    #[ORM\Column(type: 'json')]
    private array $feedback = [];

    /** An editor's reason for declining publication, until the reviewer sends the document back with it. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $editorNote = null;

    public function __construct(
        #[ORM\Id, ORM\Column(length: 32)]
        private string $id,
        #[ORM\Column(type: 'text')]
        private string $brief,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /** @param array<string, mixed> $context */
    public function setStatus(string $status, array $context = []): void
    {
        $this->status = $status;
    }

    public function brief(): string
    {
        return $this->brief;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function body(): ?string
    {
        return $this->body;
    }

    public function headline(): ?string
    {
        return $this->headline;
    }

    /** @return list<array{from: string, text: string}> */
    public function feedback(): array
    {
        return $this->feedback;
    }

    public function editorNote(): ?string
    {
        return $this->editorNote;
    }

    /** Guard of `send_back`: only an editor's decline opens that path. */
    public function hasEditorNote(): bool
    {
        return null !== $this->editorNote;
    }

    public function draft(string $title, string $body): void
    {
        $this->title = $title;
        $this->body = $body;
    }

    public function addFeedback(string $from, string $text): void
    {
        $this->feedback[] = ['from' => $from, 'text' => $text];
    }

    public function noteFromEditor(string $note): void
    {
        $this->editorNote = $note;
    }

    /** The editor's note becomes feedback for the author. */
    public function sendBack(): void
    {
        if (null !== $this->editorNote) {
            $this->addFeedback('editor', $this->editorNote);
            $this->editorNote = null;
        }
    }

    public function publish(string $headline): void
    {
        $this->headline = $headline;
    }
}
