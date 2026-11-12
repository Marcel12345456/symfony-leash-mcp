<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M6: Doc Review document subject and its event log (decisions/036).
 */
final class Version20261112120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Doc Review: document, doc_event';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE document (id VARCHAR(32) NOT NULL, status VARCHAR(32) NOT NULL, title VARCHAR(200) DEFAULT NULL, body CLOB DEFAULT NULL, headline VARCHAR(200) DEFAULT NULL, feedback CLOB NOT NULL, editor_note CLOB DEFAULT NULL, brief CLOB NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE doc_event (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, document_id VARCHAR(32) NOT NULL, type VARCHAR(32) NOT NULL, actor VARCHAR(64) NOT NULL, payload CLOB NOT NULL, at DATETIME NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE doc_event');
        $this->addSql('DROP TABLE document');
    }
}
