<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class TopdataSearchAnalyticsSW6 extends Plugin
{
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $connection = $this->container->get(Connection::class);
        $tables = [
            'tdsa_search_log',
            'tdsa_search_stats',
        ];
        foreach ($tables as $table) {
            $connection->executeStatement(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
    }
}
