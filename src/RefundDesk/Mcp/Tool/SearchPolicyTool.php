<?php

declare(strict_types=1);

namespace App\RefundDesk\Mcp\Tool;

use Symfony\Component\Security\Core\User\UserInterface;
use App\RefundDesk\Mcp\Input\SearchPolicyInput;
use Leash\WorkflowMcp\PlaceTool\AsPlaceTool;
use Leash\WorkflowMcp\PlaceTool\PlaceToolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

/** M1 keyword stub over `data/policy/*.md` sections; real RAG replaces the search in M3. */
#[AsPlaceTool(workflow: 'refund_case', name: 'refund_search_policy', description: 'Search the refund policy. Returns the best-matching policy sections for your keywords.', input: SearchPolicyInput::class)]
final readonly class SearchPolicyTool implements PlaceToolInterface
{
    private const int LIMIT = 3;

    public function __construct(
        #[Autowire('%kernel.project_dir%/data/policy')]
        private string $policyDir,
    ) {}

    public function __invoke(object $subject, ?object $input, UserInterface $caller): array
    {
        \assert($input instanceof SearchPolicyInput);
        $words = array_filter(self::split('/\W+/u', mb_strtolower($input->query)), static fn(string $w): bool => mb_strlen($w) > 2);

        $hits = [];
        foreach ((new Finder())->files()->in($this->policyDir)->name('*.md')->sortByName() as $file) {
            foreach (self::split('/^(?=## )/m', $file->getContents()) as $section) {
                if (!str_starts_with($section, '## ')) {
                    continue;
                }
                $text = mb_strtolower($section);
                $score = array_sum(array_map(static fn(string $w): int => substr_count($text, $w), $words));
                if ($score > 0) {
                    $hits[] = ['source' => $file->getFilename(), 'text' => trim($section), 'score' => $score];
                }
            }
        }
        usort($hits, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return ['query' => $input->query, 'results' => \array_slice($hits, 0, self::LIMIT)];
    }

    /** @return list<string> */
    private static function split(string $pattern, string $subject): array
    {
        $parts = preg_split($pattern, $subject, -1, \PREG_SPLIT_NO_EMPTY);

        return false === $parts ? [] : $parts;
    }
}
