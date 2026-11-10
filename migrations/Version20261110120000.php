<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M4: Werewolf game subject (the rules engine's Game, serialized) with its capability denials.
 */
final class Version20261110120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Werewolf: werewolf_game';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE werewolf_game (id VARCHAR(32) NOT NULL, status VARCHAR(32) NOT NULL, state CLOB NOT NULL, denials CLOB NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE werewolf_game');
    }
}
