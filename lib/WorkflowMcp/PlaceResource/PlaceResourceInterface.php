<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\PlaceResource;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A read-only resource attached to a workflow place. Describe it with #[AsPlaceResource].
 * Gets the caller, so a resource can show the caller's own view (e.g. a game seat's private notes).
 * Returns an assoc array (served as JSON) or text (served with the attribute's mimeType).
 */
#[AutoconfigureTag(self::TAG)]
interface PlaceResourceInterface
{
    public const string TAG = 'leash.workflow_mcp.place_resource';

    /** @return array<string, mixed>|string */
    public function __invoke(object $subject, UserInterface $caller): array|string;
}
