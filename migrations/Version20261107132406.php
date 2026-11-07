<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M2: HumanInput client side (pending inputs) and the Doctrine Messenger queue.
 */
final class Version20261107132406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'HumanInput: pending_input; messenger_messages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pending_input (id VARCHAR(32) NOT NULL, endpoint VARCHAR(1024) NOT NULL, subject_ref VARCHAR(255) NOT NULL, caller_id VARCHAR(255) NOT NULL, tool VARCHAR(255) NOT NULL, arguments CLOB NOT NULL, input_requests CLOB NOT NULL, request_state CLOB DEFAULT NULL, status VARCHAR(255) NOT NULL, responses CLOB DEFAULT NULL, result CLOB DEFAULT NULL, created_at DATETIME NOT NULL, answered_at DATETIME DEFAULT NULL, resolved_at DATETIME DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_47BDB0DF7B00651C ON pending_input (status)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pending_input');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
