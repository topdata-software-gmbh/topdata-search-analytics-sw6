<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6\Service;

/**
 * Interface for sending search analytics data to an external system (e.g. CM).
 *
 * Implementations of this interface push aggregated search statistics to
 * external APIs or data warehouses. The DatabaseSearchLogger handles local
 * persistence; implementations of this interface handle remote transmission.
 */
interface SearchAnalyticsApiClientInterface
{
    /**
     * Send a batch of search stats to the external system.
     *
     * @param array $stats Array of ['term' => string, 'count' => int, 'zero_count' => int, 'avg_result_count' => int]
     * @return int Number of stats successfully transmitted
     */
    public function pushStats(array $stats): int;

    /**
     * Check if the external API endpoint is reachable and configured.
     */
    public function isAvailable(): bool;
}
