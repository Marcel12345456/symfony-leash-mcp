<?php

declare(strict_types=1);

namespace App\Werewolf\Entity;

use App\Werewolf\Rules\Audience;
use App\Werewolf\Rules\Event;
use App\Werewolf\Rules\EventType;
use App\Werewolf\Rules\Game;
use App\Werewolf\Rules\Phase;
use App\Werewolf\Rules\Role;
use App\Werewolf\Rules\Seat;
use App\Werewolf\Rules\Winner;
use Doctrine\ORM\Mapping as ORM;

/**
 * Subject of the `werewolf_game` state machine: the rules engine's Game, persisted as a whole.
 * `status` is the workflow marking and mirrors the game phase (PhaseGuard keeps them in step).
 */
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class WerewolfGame
{
    /** Everything a serialized Game may contain; nothing else is unserialized. */
    private const array CLASSES = [Game::class, Seat::class, Event::class, Phase::class, Role::class, Audience::class, EventType::class, Winner::class];

    #[ORM\Column(length: 32)]
    private string $status;

    /** base64(serialize(Game)): private properties put NUL bytes into serialize() output. */
    #[ORM\Column(type: 'text')]
    private string $state;

    /** @var list<array{caller: string, capability: string, places: list<string>, day: int}> */
    #[ORM\Column(type: 'json')]
    private array $denials = [];

    /** Decoded from $state on first use; written back before every flush. */
    private ?Game $game = null;

    public function __construct(
        #[ORM\Id, ORM\Column(length: 32)]
        private string $id,
        Game $game,
    ) {
        $this->status = $game->phase()->value;
        $this->state = self::encode($game);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /** @param array<string, mixed> $context */
    public function setStatus(string $status, array $context = []): void
    {
        $this->status = $status;
    }

    public function game(): Game
    {
        if (null === $this->game) {
            $game = unserialize((string) base64_decode($this->state, true), ['allowed_classes' => self::CLASSES]);
            $this->game = $game instanceof Game ? $game : throw new \UnexpectedValueException(\sprintf('Werewolf game "%s" has an unreadable state.', $this->id));
        }

        return $this->game;
    }

    /** @param list<string> $places */
    public function recordDenial(string $caller, string $capability, array $places): void
    {
        $this->denials[] = ['caller' => $caller, 'capability' => $capability, 'places' => $places, 'day' => $this->game()->day()];
    }

    /** @return list<array{caller: string, capability: string, places: list<string>, day: int}> */
    public function denials(): array
    {
        return $this->denials;
    }

    #[ORM\PreFlush]
    public function writeState(): void
    {
        if (null !== $this->game) {
            $this->state = self::encode($this->game);
        }
    }

    private static function encode(Game $game): string
    {
        return base64_encode(serialize($game));
    }
}
