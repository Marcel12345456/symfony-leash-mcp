<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use Leash\HumanInput\Policy\DecliningApprovalPolicyInterface;
use Mcp\Schema\Elicitation\ElicitationSchema;
use Mcp\Schema\Request\ElicitRequest;

/** Synthetic policy: the human signs off with yes/no and may explain a no. */
final class SignoffApproval implements DecliningApprovalPolicyInterface
{
    public function inputRequest(object $subject, object $input): ElicitRequest
    {
        return ElicitRequest::forForm('Sign off?', ElicitationSchema::fromArray([
            'type' => 'object',
            'properties' => ['approve' => ['type' => 'boolean', 'title' => 'Approve'], 'note' => ['type' => 'string', 'title' => 'Note']],
            'required' => ['approve'],
        ]));
    }

    public function applyAnswer(object $input, array $content): object
    {
        return $input;
    }

    public function declineReason(array $content): ?string
    {
        if (false !== ($content['approve'] ?? null)) {
            return null;
        }
        $note = $content['note'] ?? null;

        return \is_string($note) && '' !== trim($note) ? trim($note) : 'No reason given.';
    }
}
