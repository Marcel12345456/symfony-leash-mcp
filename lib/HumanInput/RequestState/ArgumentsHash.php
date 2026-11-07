<?php

declare(strict_types=1);

namespace Leash\HumanInput\RequestState;

/** Order-independent hash of tool arguments: object keys are sorted, list order is kept. */
final class ArgumentsHash
{
    /** @param array<mixed> $arguments */
    public static function of(array $arguments): string
    {
        return hash('sha256', json_encode(self::canonical($arguments), \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array<mixed> $value
     *
     * @return array<mixed>|\stdClass
     */
    private static function canonical(array $value): array|\stdClass
    {
        $isList = array_is_list($value);
        if (!$isList) {
            ksort($value, \SORT_STRING);
        }
        foreach ($value as $key => $item) {
            if (\is_array($item)) {
                $value[$key] = self::canonical($item);
            }
        }

        // Objects encode as {} so a map never hashes like a list.
        return $isList ? $value : (object) $value;
    }
}
