<?php

declare(strict_types=1);

namespace App\DocReview\Workflow;

use App\DocReview\Entity\Document;
use App\DocReview\Mcp\Input\PublishInput;
use Leash\HumanInput\Policy\DecliningApprovalPolicyInterface;
use Mcp\Schema\Elicitation\ElicitationSchema;
use Mcp\Schema\Request\ElicitRequest;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * `editor_signoff` (PLAN §3.6, decisions/036): a human editor approves publication, may edit the headline, or says
 * no with a note. The note travels back to the author through `doc_send_back`.
 */
#[AsTaggedItem('editor_signoff')]
final readonly class EditorSignoffPolicy implements DecliningApprovalPolicyInterface
{
    public function inputRequest(object $subject, object $input): ElicitRequest
    {
        \assert($subject instanceof Document && $input instanceof PublishInput);

        return ElicitRequest::forForm(\sprintf('Publish "%s" under the headline "%s"?', $subject->title() ?? $subject->getId(), $input->headline), ElicitationSchema::fromArray([
            'type' => 'object',
            'properties' => [
                'approve' => ['type' => 'boolean', 'title' => 'Approve publication'],
                'headline' => ['type' => 'string', 'title' => 'Headline', 'default' => $input->headline, 'maxLength' => 200],
                'note' => ['type' => 'string', 'title' => 'Note for the author (when you decline)', 'maxLength' => 1000],
            ],
            'required' => ['approve'],
        ]));
    }

    public function applyAnswer(object $input, array $content): object
    {
        \assert($input instanceof PublishInput);
        $headline = \is_string($content['headline'] ?? null) ? trim($content['headline']) : '';

        return '' === $headline ? $input : new PublishInput($headline);
    }

    public function declineReason(array $content): ?string
    {
        if (false !== ($content['approve'] ?? null)) {
            return null;
        }
        $note = \is_string($content['note'] ?? null) ? trim($content['note']) : '';

        return '' === $note ? 'The editor declined without a note.' : $note;
    }
}
