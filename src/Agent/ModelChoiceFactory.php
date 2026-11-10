<?php

declare(strict_types=1);

namespace App\Agent;

use Symfony\AI\Platform\Bridge\Ollama\Factory as OllamaFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Builds the ModelChoice from `LEASH_MODEL` (decisions/028, 029).
 *
 * WORKAROUND (decisions/029): the Ollama platform is built here with its own HttpClient, not taken from the bundle,
 * so one model request has a hard timeout; a timed-out turn ends without action.
 */
final readonly class ModelChoiceFactory
{
    public function __construct(
        #[Autowire('%env(LEASH_MODEL)%')]
        private string $model,
        #[Autowire('%env(OLLAMA_ENDPOINT)%')]
        private string $ollamaEndpoint,
        #[Autowire('%env(LEASH_OLLAMA_MODEL)%')]
        private string $ollamaModel,
        #[Autowire('%env(bool:LEASH_THINK)%')]
        private bool $think,
        #[Autowire('%env(float:LEASH_TURN_TIMEOUT)%')]
        private float $timeoutSeconds,
    ) {}

    public function create(?string $model = null): ModelChoice
    {
        return match ($model ?? $this->model) {
            'ollama' => $this->ollama(),
            'replay' => throw new \LogicException('LEASH_MODEL=replay needs the ReplayPlatform (decisions/028), which is not built yet. Use LEASH_MODEL=ollama.'),
            default => throw new \InvalidArgumentException(\sprintf('LEASH_MODEL "%s" is unknown; use replay or ollama.', $model ?? $this->model)),
        };
    }

    private function ollama(): ModelChoice
    {
        if ('' === $this->ollamaModel) {
            throw new \InvalidArgumentException('LEASH_OLLAMA_MODEL is empty.');
        }
        $http = HttpClient::create(['timeout' => $this->timeoutSeconds, 'max_duration' => $this->timeoutSeconds]);

        return new ModelChoice(OllamaFactory::createPlatform($this->ollamaEndpoint, null, $http), $this->ollamaModel, $this->think);
    }
}
