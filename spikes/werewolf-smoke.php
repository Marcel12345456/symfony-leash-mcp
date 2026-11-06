<?php

declare(strict_types=1);

/*
 * Phase 0 task 6 — Werewolf smoke test (plain prompts, no engine, no library code).
 * One discussion round + one vote, 6 seats, per model. Transcripts go to spikes/out/.
 * Usage (toolchain container): php spikes/werewolf-smoke.php qwen2.5:7b qwen3:latest
 * Cost: Ollama only (local, $0). Paid providers are not wired here on purpose (SpendGuard does not exist yet).
 */

use App\Kernel;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';
(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
// The platform service is private; the test container exposes it without touching app config (spike only).
$kernel = new Kernel('test', false);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
assert($container instanceof Psr\Container\ContainerInterface);
$platform = $container->get('ai.platform.ollama');
assert($platform instanceof PlatformInterface);

$models = array_slice($argv, 1) ?: ['qwen2.5:7b'];
$names = ['P1' => 'Ada', 'P2' => 'Ben', 'P3' => 'Cleo', 'P4' => 'Dan', 'P5' => 'Eve', 'P6' => 'Finn'];
$roles = ['P1' => 'villager', 'P2' => 'werewolf', 'P3' => 'seer', 'P4' => 'villager', 'P5' => 'werewolf', 'P6' => 'villager'];
$private = [
    'P2' => 'Your fellow werewolf is P5 (Eve). Do not reveal your role. Deflect suspicion onto a villager.',
    'P5' => 'Your fellow werewolf is P2 (Ben). Do not reveal your role. Deflect suspicion onto a villager.',
    'P3' => 'Last night you inspected P2 (Ben): Ben is a WEREWOLF.',
];
$rules = 'You are playing the social deduction game Werewolf with 6 players: '
    . implode(', ', array_map(fn($id, $n) => "$id ($n)", array_keys($names), $names)) . '. '
    . 'There are 2 werewolves, 1 seer and 3 villagers. It is the first day; nobody has died yet. '
    . 'Villagers win by voting out the werewolves; werewolves win by staying hidden.';

$summary = [];
foreach ($models as $model) {
    $started = microtime(true);
    $tokens = 0;
    $ask = function (string $id, string $task) use ($platform, $model, $rules, $roles, $private, $names, &$tokens): string {
        $system = $rules . "\nYou are $id ({$names[$id]}). Your secret role: {$roles[$id]}. " . ($private[$id] ?? '');
        $result = $platform->invoke($model, new MessageBag(Message::forSystem($system), Message::ofUser($task)))->getResult();
        $usage = $result->getMetadata()->get('token_usage');
        if (is_object($usage) && method_exists($usage, 'getPromptTokens')) {
            $tokens += ($usage->getPromptTokens() ?? 0) + ($usage->getCompletionTokens() ?? 0);
        }
        $text = $result->getContent();

        return trim((string) preg_replace('/<think>.*?<\/think>/s', '', is_string($text) ? $text : ''));
    };

    $discussion = [];
    foreach ($names as $id => $name) {
        $log = implode("\n", array_map(fn($line) => "{$line['id']} ({$names[$line['id']]}): {$line['text']}", $discussion));
        $statement = $ask($id, "Discussion so far:\n" . ('' === $log ? '(you speak first)' : $log)
            . "\n\nSay one short statement (max 3 sentences) to the group. Reply with the statement only.");
        $discussion[] = ['id' => $id, 'text' => $statement];
    }

    $log = implode("\n", array_map(fn($line) => "{$line['id']} ({$names[$line['id']]}): {$line['text']}", $discussion));
    $votes = [];
    foreach ($names as $id => $name) {
        $raw = $ask($id, "Discussion:\n$log\n\nVote to eliminate one other player. Reply with JSON only: "
            . '{"vote": "P<number>", "reason": "<one sentence referring to the discussion>"}');
        preg_match('/\{.*\}/s', $raw, $m);
        $json = json_decode($m[0] ?? '', true);
        $vote = is_array($json) && is_string($json['vote'] ?? null) ? strtoupper(trim($json['vote'])) : null;
        $reason = is_array($json) && is_string($json['reason'] ?? null) ? $json['reason'] : $raw;
        $votes[] = ['id' => $id, 'vote' => $vote, 'reason' => $reason, 'raw' => $raw];
    }

    // Mechanical coherence signals (the human still reads the transcript).
    $valid = count(array_filter($votes, fn($v) => isset($names[$v['vote'] ?? '']) && $v['vote'] !== $v['id']));
    $referencing = count(array_filter($votes, function ($v) use ($names) {
        foreach ($names as $pid => $pname) {
            if ($pid !== $v['id'] && (str_contains($v['reason'], $pid) || str_contains($v['reason'], $pname))) {
                return true;
            }
        }

        return false;
    }));
    $wolfSelfOuts = count(array_filter($discussion, fn($l) => 'werewolf' === $roles[$l['id']]
        && preg_match('/\bI(\'m| am) (a |the |one of the )?(were)?wol(f|ves)\b/i', $l['text'])));
    $seerAccusedBen = (bool) preg_match('/\b(P2|Ben)\b/', $discussion[2]['text']);
    $wolfVotesOnWolf = count(array_filter($votes, fn($v) => 'werewolf' === $roles[$v['id']] && in_array($v['vote'], ['P2', 'P5'], true)));
    $tally = array_count_values(array_filter(array_column($votes, 'vote'), 'is_string'));
    arsort($tally);

    $row = [
        'model' => $model,
        'seconds' => round(microtime(true) - $started, 1),
        'tokens' => $tokens,
        'valid_votes' => "$valid/6",
        'reasons_referencing_players' => "$referencing/6",
        'wolf_self_outs' => $wolfSelfOuts,
        'seer_named_ben' => $seerAccusedBen,
        'wolves_voting_wolf' => $wolfVotesOnWolf,
        'eliminated' => array_key_first($tally),
    ];
    $summary[] = $row;

    $file = __DIR__ . '/out/' . preg_replace('/[^a-z0-9.]+/i', '-', $model) . '.json';
    file_put_contents($file, json_encode(['summary' => $row, 'roles' => $roles, 'discussion' => $discussion, 'votes' => $votes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
    echo json_encode($row) . "\n";
}
