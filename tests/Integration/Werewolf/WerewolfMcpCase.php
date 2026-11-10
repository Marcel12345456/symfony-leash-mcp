<?php

declare(strict_types=1);

namespace App\Tests\Integration\Werewolf;

use App\Tests\Integration\Support\KernelBrowserPsr18Client;
use App\Werewolf\Entity\WerewolfGame;
use App\Werewolf\Rules\Game;
use App\Werewolf\Rules\Role;
use App\Werewolf\Security\WerewolfCallers;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Mcp\Client;
use Mcp\Client\Transport\HttpTransport;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Tool;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Werewolf over the real MCP endpoint: in-process HTTP, the mcp/sdk client, per-seat bearer tokens. */
abstract class WerewolfMcpCase extends WebTestCase
{
    protected const string SECRET = 'test-werewolf-secret';

    /** Fixed roles: wolves 1 and 5, seer 3, villagers 2, 4, 6. */
    protected const array ROLES = ['Anna' => Role::Wolf, 'Ben' => Role::Villager, 'Cara' => Role::Seer, 'Dan' => Role::Villager, 'Eve' => Role::Wolf, 'Finn' => Role::Villager];

    protected KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $em = $this->entityManager();
        $schema = new SchemaTool($em);
        $schema->dropDatabase();
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());
    }

    protected function createGame(string $id, Game $game): void
    {
        $em = $this->entityManager();
        $em->persist(new WerewolfGame($id, $game));
        $em->flush();
        $em->clear();
    }

    protected function game(string $id): WerewolfGame
    {
        $em = $this->entityManager();
        $em->clear();

        return $em->find(WerewolfGame::class, $id) ?? throw new \LogicException('No game ' . $id);
    }

    /** @param string $caller `seat:N` or the moderator */
    protected function connect(string $caller, string $gameId): Client
    {
        $factory = new Psr17Factory();
        $client = Client::builder()->setClientInfo('leash-werewolf-test', '0.1')->build();
        $client->connect(new HttpTransport(
            'http://localhost/mcp/werewolf_game/' . $gameId,
            ['Authorization' => 'Bearer ' . WerewolfCallers::token(self::SECRET, $caller)],
            new KernelBrowserPsr18Client($this->browser, $factory),
            $factory,
            $factory,
        ));

        return $client;
    }

    /** @return list<string> */
    protected static function toolNames(Client $client): array
    {
        return array_values(array_map(static fn(Tool $t): string => $t->name, $client->listTools()->tools));
    }

    /** @return list<string> resource names */
    protected static function resourceNames(Client $client): array
    {
        return array_values(array_map(static fn(ResourceDefinition $r): string => $r->name, $client->listResources()->resources));
    }

    /** @return array<array-key, mixed> */
    protected static function read(Client $client, string $gameId, string $name): array
    {
        $content = $client->readResource(\sprintf('werewolf://game/%s/%s', $gameId, $name))->contents[0] ?? null;
        self::assertInstanceOf(TextResourceContents::class, $content);
        $data = json_decode($content->text, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    protected static function text(CallToolResult $result): string
    {
        $content = $result->content[0] ?? null;

        return $content instanceof TextContent && \is_string($content->text) ? $content->text : '';
    }

    protected function entityManager(): EntityManagerInterface
    {
        $em = $this->browser->getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }
}
