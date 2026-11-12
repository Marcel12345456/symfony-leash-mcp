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
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * `make work`: the Doc Review agents work every open document once, until each waits for a human or is done.
 * Replay (default `LEASH_MODEL`) plays the committed cassette, recorded from the seeded documents (decisions/038).
 */
#[AsCommand('leash:work', 'Run the Doc Review agents over all open documents')]
final readonly class WorkCommand
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocWorkRunner $runner,
        private ModelChoiceFactory $models,
        #[Autowire('%kernel.project_dir%/data/docreview/demo.json')]
        private string $defaultCassette,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'replay or ollama (default: LEASH_MODEL)')]
        ?string $model = null,
        #[Option(description: 'Record the live run into the cassette (ollama only); start from freshly seeded documents')]
        bool $record = false,
        #[Option(description: 'Cassette path (default: data/docreview/demo.json)')]
        ?string $cassette = null,
    ): int {
        $live = 'ollama' === ($model ?? $this->models->defaultModel());
        if ($record && !$live) {
            $io->error('--record needs the live model: --model=ollama (or LEASH_MODEL=ollama).');

            return Command::INVALID;
        }
        $cassette ??= $this->defaultCassette;
        $choice = $this->models->create($model, $live && !$record ? null : $cassette, agents: ModelChoiceFactory::DOC_REVIEW);
        $open = array_filter($this->entityManager->getRepository(Document::class)->findBy([], ['id' => 'ASC']), static fn(Document $d): bool => 'published' !== $d->getStatus());
        foreach (array_map(static fn(Document $d): string => $d->getId(), $open) as $id) {
            $io->section('Document ' . $id);
            $turns = $this->runner->work($id, $choice);
            $io->listing([] === $turns ? ['nobody acts'] : $turns);
        }
        $left = $choice->replay?->remaining() ?? 0;
        if (0 !== $left) {
            $io->error(\sprintf('%d recorded model answers unused; the run diverged from the cassette %s (start from `leash:demo:seed`).', $left, $cassette));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
