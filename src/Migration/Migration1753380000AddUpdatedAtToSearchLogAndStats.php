<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1753380000AddUpdatedAtToSearchLogAndStats extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1753380000;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn($connection, 'tdsa_search_log', 'updated_at', 'DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)');
        $this->addColumn($connection, 'tdsa_search_stats', 'updated_at', 'DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3)');
    }

    public function updateDestructive(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `tdsa_search_log` DROP COLUMN `updated_at`');
        $connection->executeStatement('ALTER TABLE `tdsa_search_stats` DROP COLUMN `updated_at`');
    }
}