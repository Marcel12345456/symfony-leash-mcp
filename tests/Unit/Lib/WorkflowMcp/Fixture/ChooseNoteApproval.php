<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use Leash\HumanInput\Policy\ApprovalPolicyInterface;
use Mcp\Schema\Elicitation\ElicitationSchema;
use Mcp\Schema\Request\ElicitRequest;

/** Synthetic policy with a titled enum: the human picks the release note from a fixed list. */
final class ChooseNoteApproval implements ApprovalPolicyInterface
{
    public const array NOTES = ['ship-monday' => 'Ship on Monday', 'ship-friday' => 'Ship on Friday'];

    public function inputRequest(object $subject, object $input): ElicitRequest
    {
        $options = [];
        foreach (self::NOTES as $const => $title) {
            $options[] = ['const' => $const, 'title' => $title];
        }

        return ElicitRequest::forForm('When do we ship?', ElicitationSchema::fromArray([
            'type' => 'object',
            'properties' => ['note' => ['type' => 'string', 'title' => 'Release', 'oneOf' => $options]],
            'required' => ['note'],
        ]));
    }

    public function applyAnswer(object $input, array $content): object
    {
        $note = $content['note'] ?? null;

        return \is_string($note) && isset(self::NOTES[$note]) ? new ApproveInput($note) : $input;
    }
}
