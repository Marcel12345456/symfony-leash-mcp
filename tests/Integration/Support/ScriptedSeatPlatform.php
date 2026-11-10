<?php

declare(strict_types=1);

namespace App\Tests\Integration\Support;

use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Test\MockPlatformFactory;

/**
 * Scripted model per seat (decisions/030): finds the seat in the turn message of the model input (model, payload, options) ("You are seat N"), pops that seat's
 * next result (a Throwable is thrown) and records which seats were asked. An empty script answers "done".
 */
final class ScriptedSeatPlatform
{
    /** @var list<int> */
    public array $asked = [];

    /** @param array<int, list<ResultInterface|string|\Throwable>> $script */
    public function __construct(private array $script = []) {}

    public function platform(): PlatformInterface
    {
        return MockPlatformFactory::createPlatform(function (mixed ...$arguments): ResultInterface|string {
            $seat = 1 === preg_match('/You are seat (\d+)/', implode("\n", array_map(self::text(...), $arguments)), $m) ? (int) $m[1] : throw new \LogicException('No seat in the model input.');
            $this->asked[] = $seat;
            $next = array_shift($this->script[$seat]) ?? 'done';

            return $next instanceof \Throwable ? throw $next : $next;
        });
    }

    private static function text(mixed $payload): string
    {
        if (!$payload instanceof MessageBag) {
            return (string) json_encode($payload);
        }
        $texts = [];
        foreach ($payload->getMessages() as $message) {
            if ($message instanceof UserMessage) {
                $texts[] = (string) $message->asText();
            }
        }

        return implode("\n", $texts);
    }
}
