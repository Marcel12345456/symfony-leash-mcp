<?php

declare(strict_types=1);

namespace App\Agent\DocReview;

use App\Agent\ModelChoiceFactory;
use App\DocReview\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** `make work`: the Doc Review agents work every open document once, until each waits for a human or is done. */
#[AsCommand('leash:work', 'Run the Doc Review agents over all open documents')]
final readonly class WorkCommand
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocWorkRunner $runner,
        private ModelChoiceFactory $models,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'ollama (default: LEASH_MODEL; replay is not available for Doc Review yet)')]
        ?string $model = null,
    ): int {
        $choice = $this->models->create($model);
        $open = array_filter($this->entityManager->getRepository(Document::class)->findAll(), static fn(Document $d): bool => 'published' !== $d->getStatus());
        foreach (array_map(static fn(Document $d): string => $d->getId(), $open) as $id) {
            $io->section('Document ' . $id);
            $turns = $this->runner->work($id, $choice);
            $io->listing([] === $turns ? ['nobody acts'] : $turns);
        }

        return Command::SUCCESS;
    }
}
