<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ON DELETE CASCADE to user_article_read, user_article_bookmark, and notification_log article FKs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_article_read DROP CONSTRAINT fk_a8d9ba647294869c');
        $this->addSql('ALTER TABLE user_article_read ADD CONSTRAINT fk_a8d9ba647294869c FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE user_article_bookmark DROP CONSTRAINT fk_f29f24ab7294869c');
        $this->addSql('ALTER TABLE user_article_bookmark ADD CONSTRAINT fk_f29f24ab7294869c FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE notification_log DROP CONSTRAINT fk_ed15df27294869c');
        $this->addSql('ALTER TABLE notification_log ADD CONSTRAINT fk_ed15df27294869c FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_article_read DROP CONSTRAINT fk_a8d9ba647294869c');
        $this->addSql('ALTER TABLE user_article_read ADD CONSTRAINT fk_a8d9ba647294869c FOREIGN KEY (article_id) REFERENCES article (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE user_article_bookmark DROP CONSTRAINT fk_f29f24ab7294869c');
        $this->addSql('ALTER TABLE user_article_bookmark ADD CONSTRAINT fk_f29f24ab7294869c FOREIGN KEY (article_id) REFERENCES article (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE notification_log DROP CONSTRAINT fk_ed15df27294869c');
        $this->addSql('ALTER TABLE notification_log ADD CONSTRAINT fk_ed15df27294869c FOREIGN KEY (article_id) REFERENCES article (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
