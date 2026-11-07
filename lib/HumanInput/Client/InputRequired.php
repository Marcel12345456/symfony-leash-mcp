<?php

declare(strict_types=1);

namespace Leash\HumanInput\Client;

/**
 * Client view of an `input_required` result, kept as raw wire data (decisions/020):
 * the SDK's `InputRequiredResult` has no `fromArray`, and the asks must survive storage unchanged.
 */
final readonly class InputRequired
{
    /** @param array<string, array<string, mixed>> $inputRequests key => `{method, params}` */
    public function __construct(
        public array $inputRequests,
        public ?string $requestState,
    ) {}

    /** @param array<mixed> $result a decoded `tools/call` result */
    public static function fromResult(array $result): ?self
    {
        if ('input_required' !== ($result['resultType'] ?? null)) {
            return null;
        }

        $requests = [];
        foreach (\is_array($result['inputRequests'] ?? null) ? $result['inputRequests'] : [] as $key => $request) {
            if (\is_array($request)) {
                $requests[(string) $key] = self::object($request);
            }
        }
        $state = $result['requestState'] ?? null;

        return new self($requests, \is_string($state) ? $state : null);
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function object(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }
}
