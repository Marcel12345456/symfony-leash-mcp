<?php

declare(strict_types=1);

namespace App\Agent;

use App\Agent\Replay\RecordingSeatProvider;
use App\Agent\Replay\ReplayPlatform;
use App\Agent\Replay\SeatCassette;
use App\Agent\Werewolf\ScriptedVillager;
use Symfony\AI\Platform\Bridge\Ollama\Factory as OllamaFactory;
use Symfony\AI\Platform\Platform;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Builds the ModelChoice from `LEASH_MODEL` (decisions/029, 033): `ollama` live, optionally recording a cassette;
 * `replay` from a cassette recorded with the same player prompt.
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
        #[Autowire('%kernel.project_dir%/config/prompts/werewolf/player.md')]
        private string $promptFile,
    ) {}

    /**
     * @param ?string $cassette     replay: the cassette to play; ollama: the cassette to record into (null = no recording)
     * @param ?int    $scriptedSeat ollama: the villager seat that plays the fixed script; replay takes it from the cassette
     */
    public function create(?string $model = null, ?string $cassette = null, ?int $scriptedSeat = null): ModelChoice
    {
        $model ??= $this->model;

        return match ($model) {
            'ollama' => $this->ollama($cassette, $scriptedSeat),
            'replay' => $this->replay($cassette ?? throw new \InvalidArgumentException('LEASH_MODEL=replay needs a cassette (leash:werewolf:play --cassette).')),
            default => throw new \InvalidArgumentException(\sprintf('LEASH_MODEL "%s" is unknown; use replay or ollama.', $model)),
        };
    }

    public function defaultModel(): string
    {
        return $this->model;
    }

    public function promptHash(): string
    {
        $prompt = file_get_contents($this->promptFile);

        return hash('xxh128', false === $prompt ? throw new \RuntimeException('Missing prompt ' . $this->promptFile) : $prompt);
    }

    private function ollama(?string $record, ?int $scriptedSeat): ModelChoice
    {
        if ('' === $this->ollamaModel) {
            throw new \InvalidArgumentException('LEASH_OLLAMA_MODEL is empty.');
        }
        $http = HttpClient::create(['timeout' => $this->timeoutSeconds, 'max_duration' => $this->timeoutSeconds]);
        $provider = OllamaFactory::createProvider($this->ollamaEndpoint, null, $http);
        if (null !== $record) {
            $provider = new RecordingSeatProvider($provider, SeatCassette::start($record, $this->ollamaModel, $this->promptHash(), $scriptedSeat));
        }

        return new ModelChoice(new Platform([$provider]), $this->ollamaModel, $this->think, scripted: self::scripted($scriptedSeat));
    }

    private function replay(string $cassette): ModelChoice
    {
        $loaded = SeatCassette::load($cassette, $this->promptHash());
        $replay = new ReplayPlatform($loaded);

        return new ModelChoice($replay->platform(), $loaded->model, replay: $replay, scripted: self::scripted($loaded->scriptedSeat));
    }

    private static function scripted(?int $seat): ?ScriptedVillager
    {
        return null === $seat ? null : new ScriptedVillager($seat);
    }
}
