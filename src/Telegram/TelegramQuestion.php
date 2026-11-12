<?php

declare(strict_types=1);

namespace App\Telegram;

use Leash\HumanInput\Pending\PendingInput;

/**
 * A pending input that fits on buttons (decisions/034): one ask with one field that is a boolean or a pick list.
 * Anything else is answered in the inbox.
 */
final readonly class TelegramQuestion
{
    /** @param list<array{label: string, value: string|bool|int|float}> $options */
    private function __construct(
        public string $key,
        public string $field,
        public string $message,
        public array $options,
    ) {}

    public static function of(PendingInput $pending): ?self
    {
        if (1 !== \count($pending->inputRequests)) {
            return null;
        }
        $key = array_key_first($pending->inputRequests);
        $params = $pending->inputRequests[$key]['params'] ?? null;
        $properties = \is_array($params) && \is_array($params['requestedSchema'] ?? null) ? ($params['requestedSchema']['properties'] ?? null) : null;
        if (!\is_array($properties) || 1 !== \count($properties) || !\is_array($property = reset($properties))) {
            return null;
        }
        $options = self::options($property);

        return [] === $options ? null : new self(
            $key,
            (string) array_key_first($properties),
            \is_string($params['message'] ?? null) ? $params['message'] : $pending->tool,
            $options,
        );
    }

    /**
     * @param array<mixed> $property
     *
     * @return list<array{label: string, value: string|bool|int|float}>
     */
    private static function options(array $property): array
    {
        if ('boolean' === ($property['type'] ?? null)) {
            return [['label' => 'Yes', 'value' => true], ['label' => 'No', 'value' => false]];
        }
        $options = [];
        if (\is_array($property['oneOf'] ?? null)) {
            foreach ($property['oneOf'] as $option) {
                if (\is_array($option) && \is_scalar($option['const'] ?? null)) {
                    $options[] = ['label' => \is_string($option['title'] ?? null) ? $option['title'] : (string) $option['const'], 'value' => $option['const']];
                }
            }
        } elseif (\is_array($property['enum'] ?? null)) {
            $names = \is_array($property['enumNames'] ?? null) ? $property['enumNames'] : [];
            foreach (array_values($property['enum']) as $i => $value) {
                if (\is_scalar($value)) {
                    $options[] = ['label' => \is_string($names[$i] ?? null) ? $names[$i] : (string) $value, 'value' => $value];
                }
            }
        }

        return $options;
    }
}
