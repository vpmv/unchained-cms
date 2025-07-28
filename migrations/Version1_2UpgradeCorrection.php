<?php

namespace migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version1_2UpgradeCorrection extends AbstractMigration
{
    /**
     * @inheritDoc
     */
    public function up(Schema $schema): void
    {
        $this->connection->beginTransaction();

    }


    private function horses(Schema $schema): void
    {

    }

}