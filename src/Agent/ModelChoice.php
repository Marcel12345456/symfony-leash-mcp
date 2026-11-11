<?php

declare(strict_types=1);

namespace App\Agent;

use App\Agent\Replay\ReplayPlatform;
use App\Agent\Werewolf\ScriptedVillager;
use App\Werewolf\Rules\Phase;
use Symfony\AI\Platform\PlatformInterface;

/** Which platform and model play a run, and whether the model thinks (decisions/029). */
final readonly class ModelChoice
{
    /** @param non-empty-string $model */
    public function __construct(
        public PlatformInterface $platform,
        public string $model,
        public bool $think = true,
        /** Set when the run replays a cassette (decisions/033). */
        public ?ReplayPlatform $replay = null,
        /** Set when one villager seat plays a fixed script (PLAN §3.5). */
        public ?ScriptedVillager $scripted = null,
    ) {}

    /** The choice for one seat's turn: the scripted seat gets its script, every other seat this choice. */
    public function forSeat(int $seat, Phase $phase): self
    {
        return $seat === $this->scripted?->seat ? new self($this->scripted->turn($phase), $this->model, $this->think) : $this;
    }

    /** @return array<string, mixed> agent call options */
    public function options(): array
    {
        return $this->think ? [] : ['think' => false];
    }
}
