<?php

declare(strict_types=1);

namespace App\Tests\Unit\Agent;

use App\Agent\Werewolf\ScriptedVillager;
use App\Werewolf\Rules\Phase;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\ToolCallResult;

final class ScriptedVillagerTest extends TestCase
{
    public function testFirstStatementTurnTriesTheWolfToolThenInjects(): void
    {
        $answers = self::answers(new ScriptedVillager(6)->turn(Phase::DayDiscussion), 3);

        self::assertSame(['ww_choose_victim', ['target' => 1]], $answers[0]);
        self::assertSame(['ww_say', ['message' => ScriptedVillager::INJECTION]], $answers[1]);
        self::assertIsString($answers[2]);
    }

    public function testSecondStatementIsPlainThenItStaysSilentAndNeverVotes(): void
    {
        $villager = new ScriptedVillager(6);
        $villager->turn(Phase::DayDiscussion);

        self::assertSame(['ww_say', ['message' => ScriptedVillager::SECOND_STATEMENT]], self::answers($villager->turn(Phase::DayDiscussion), 1)[0]);
        self::assertIsString(self::answers($villager->turn(Phase::DayDiscussion), 1)[0]);
        self::assertIsString(self::answers($villager->turn(Phase::DayVote), 1)[0]);
    }

    public function testInjectionFitsInOneStatement(): void
    {
        self::assertLessThanOrEqual(300, mb_strlen(ScriptedVillager::INJECTION));
        self::assertLessThanOrEqual(300, mb_strlen(ScriptedVillager::SECOND_STATEMENT));
    }

    /** @return list<string|array{string, array<string, mixed>}> text, or [tool, arguments] for a tool call */
    private static function answers(PlatformInterface $platform, int $count): array
    {
        $answers = [];
        for ($i = 0; $i < $count; ++$i) {
            $result = $platform->invoke('scripted', 'turn')->getResult();
            if ($result instanceof ToolCallResult) {
                $call = $result->getContent()[0];
                $answers[] = [$call->getName(), $call->getArguments()];
                continue;
            }
            $text = $result->getContent();
            self::assertIsString($text);
            $answers[] = $text;
        }

        return $answers;
    }
}
