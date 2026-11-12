<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M6: the Refund Desk is retired (decisions/022; code at tag refund-desk-v1). Drops the tables of Version20261107113832.
 * `down()` recreates them empty, with the same SQL.
 */
final class Version20261112130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retire the Refund Desk: drop case_event, refund, refund_case, orders, customer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE case_event');
        $this->addSql('DROP TABLE refund');
        $this->addSql('DROP TABLE refund_case');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE customer');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE case_event (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              type VARCHAR(32) NOT NULL,
              actor VARCHAR(64) NOT NULL,
              payload CLOB NOT NULL,
              created_at DATETIME NOT NULL,
              case_id VARCHAR(16) NOT NULL,
              CONSTRAINT FK_6947F04BCF10D4F5 FOREIGN KEY (case_id) REFERENCES refund_case (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_6947F04BCF10D4F58B8E8428 ON case_event (case_id, created_at)');
        $this->addSql('CREATE INDEX IDX_6947F04BCF10D4F5 ON case_event (case_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE customer (
              id VARCHAR(16) NOT NULL,
              name VARCHAR(120) NOT NULL,
              email VARCHAR(180) NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE orders (
              id VARCHAR(16) NOT NULL,
              item VARCHAR(200) NOT NULL,
              amount_cents INTEGER NOT NULL,
              delivered_at DATETIME NOT NULL,
              customer_id VARCHAR(16) NOT NULL,
              PRIMARY KEY (id),
              CONSTRAINT FK_E52FFDEE9395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_E52FFDEE9395C3F3 ON orders (customer_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE refund (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              amount_cents INTEGER NOT NULL,
              proposed_amount_cents INTEGER NOT NULL,
              provider_reference VARCHAR(64) NOT NULL,
              created_at DATETIME NOT NULL,
              case_id VARCHAR(16) NOT NULL,
              CONSTRAINT FK_5B2C1458CF10D4F5 FOREIGN KEY (case_id) REFERENCES refund_case (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_5B2C1458CF10D4F5 ON refund (case_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE refund_case (
              status VARCHAR(32) NOT NULL,
              decision VARCHAR(255) DEFAULT NULL,
              proposed_amount_cents INTEGER DEFAULT NULL,
              rationale CLOB DEFAULT NULL,
              customer_reply CLOB DEFAULT NULL,
              id VARCHAR(16) NOT NULL,
              customer_message CLOB NOT NULL,
              opened_at DATETIME NOT NULL,
              customer_id VARCHAR(16) NOT NULL,
              order_id VARCHAR(16) DEFAULT NULL,
              PRIMARY KEY (id),
              CONSTRAINT FK_9A2D96C69395C3F3 FOREIGN KEY (customer_id) REFERENCES customer (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_9A2D96C68D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_9A2D96C69395C3F3 ON refund_case (customer_id)');
        $this->addSql('CREATE INDEX IDX_9A2D96C68D9F6D38 ON refund_case (order_id)');
    }
}
