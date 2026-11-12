<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** M6 human seat (decisions/035): the seat a human plays through the inbox, per game. */
final class Version20261112140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Werewolf: werewolf_game.human_seat';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE werewolf_game ADD COLUMN human_seat INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE werewolf_game DROP COLUMN human_seat');
    }
}
