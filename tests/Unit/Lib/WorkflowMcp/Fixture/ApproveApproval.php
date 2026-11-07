<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use Leash\HumanInput\Policy\ApprovalPolicyInterface;
use Mcp\Schema\Elicitation\ElicitationSchema;
use Mcp\Schema\Elicitation\StringSchemaDefinition;
use Mcp\Schema\Request\ElicitRequest;

/** Synthetic policy: a human confirms every approval and may rewrite the note (like lowering an amount). */
final class ApproveApproval implements ApprovalPolicyInterface
{
    public function inputRequest(object $subject, object $input): ElicitRequest
    {
        \assert($input instanceof ApproveInput);

        return ElicitRequest::forForm(
            \sprintf('Publish with note "%s"?', $input->note),
            new ElicitationSchema(['note' => new StringSchemaDefinition('Note', default: $input->note)], []),
        );
    }

    public function applyAnswer(object $input, array $content): object
    {
        \assert($input instanceof ApproveInput);
        $note = $content['note'] ?? null;

        return \is_string($note) && '' !== $note ? new ApproveInput($note) : $input;
    }
}
