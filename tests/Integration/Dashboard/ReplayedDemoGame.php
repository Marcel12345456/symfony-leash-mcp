<?php

declare(strict_types=1);

namespace App\Tests\Integration\Dashboard;

use App\Agent\ModelChoiceFactory;
use App\Agent\Werewolf\NextActorRunner;
use App\Agent\Werewolf\PlayGameCommand;
use App\Agent\Werewolf\StepOutcome;
use App\Werewolf\Entity\WerewolfGame;
use App\Werewolf\Rules\Game;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Replays the committed demo cassette (seed 8) step by step through the runner, with no model (decisions/033). */
final readonly class ReplayedDemoGame
{
    public const string ID = 'seed-8';

    /**
     * @param \Closure(int $step): void $afterEachStep also called once with 0 before the first step
     *
     * @return int steps played
     */
    public static function play(ContainerInterface $container, ?\Closure $afterEachStep = null): int
    {
        $em = self::service($container, EntityManagerInterface::class);
        $runner = self::service($container, NextActorRunner::class);
        $models = self::service($container, ModelChoiceFactory::class);

        $em->persist(new WerewolfGame(self::ID, Game::start(8, PlayGameCommand::NAMES)));
        $em->flush();
        $em->clear();
        $choice = $models->create('replay', \dirname(__DIR__, 3) . '/data/werewolf/seed-8.json');

        $afterEachStep?->__invoke(0);
        for ($n = 1; $n <= 60; ++$n) {
            $step = $runner->step(self::ID, $choice);
            $afterEachStep?->__invoke($n);
            if ($step->gameOver) {
                return $n;
            }
            if (StepOutcome::Played !== $step->outcome) {
                throw new \LogicException(\sprintf('Replay stopped at step %d: %s.', $n, $step->outcome->value));
            }
        }

        throw new \LogicException('The demo replay did not finish within 60 steps.');
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    public static function service(ContainerInterface $container, string $id): object
    {
        $service = $container->get($id);

        return $service instanceof $id ? $service : throw new \LogicException('No service ' . $id);
    }
}
