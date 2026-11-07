<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M2: HumanInput idempotency (nonce → stored result).
 */
final class Version20261107125409 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'HumanInput: processed_input_request';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE processed_input_request (
              nonce VARCHAR(64) NOT NULL,
              result CLOB NOT NULL,
              processed_at DATETIME NOT NULL,
              PRIMARY KEY (nonce)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE processed_input_request');
    }
}
