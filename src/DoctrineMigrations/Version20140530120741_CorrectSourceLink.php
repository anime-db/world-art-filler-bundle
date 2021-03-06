<?php
/**
 * AnimeDb package.
 *
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\WorldArtFillerBundle\DoctrineMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20140530120741_CorrectSourceLink extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->addSql('
            UPDATE
                `source`
            SET
                `url` = replace(`url`, "http://www.world-art.ruanimation/", "http://www.world-art.ru/animation/")
            WHERE
                `url` LIKE "http://www.world-art.ruanimation/%"'
        );
        $this->addSql('
            UPDATE
                `source`
            SET
                `url` = replace(`url`, "http://www.world-art.rucinema/", "http://www.world-art.ru/cinema/")
            WHERE
                `url` LIKE "http://www.world-art.rucinema/%"'
        );
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        $this->skipIf(true, 'No need to migrate');
    }
}
