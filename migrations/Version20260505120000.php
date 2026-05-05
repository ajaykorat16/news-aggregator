<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ON DELETE CASCADE to source.category_id and article.category_id foreign keys';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source DROP CONSTRAINT fk_5f8a7f7312469de2');
        $this->addSql('ALTER TABLE source ADD CONSTRAINT fk_5f8a7f7312469de2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE article DROP CONSTRAINT fk_23a0e6612469de2');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT fk_23a0e6612469de2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source DROP CONSTRAINT fk_5f8a7f7312469de2');
        $this->addSql('ALTER TABLE source ADD CONSTRAINT fk_5f8a7f7312469de2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE article DROP CONSTRAINT fk_23a0e6612469de2');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT fk_23a0e6612469de2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
