<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ON DELETE CASCADE to article.source_id foreign key';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP CONSTRAINT fk_23a0e66953c1c61');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT fk_23a0e66953c1c61 FOREIGN KEY (source_id) REFERENCES source (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP CONSTRAINT fk_23a0e66953c1c61');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT fk_23a0e66953c1c61 FOREIGN KEY (source_id) REFERENCES source (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
