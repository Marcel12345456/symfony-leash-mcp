<?php

declare(strict_types=1);

namespace App\Spike;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/** Phase 0 Proof 2 (spike): a tool the model must call; records its calls so the command can prove it. */
#[AsTool('spike_case_amount', 'Returns the order amount of a refund case in integer cents. Use it whenever you need a case amount.')]
final class CaseAmountTool
{
    /** @var list<string> */
    public array $calls = [];

    /**
     * @param string $caseId Case id, e.g. "C1"
     */
    public function __invoke(string $caseId): int
    {
        $this->calls[] = $caseId;

        return 'C1' === $caseId ? 4250 : 0;
    }
}
