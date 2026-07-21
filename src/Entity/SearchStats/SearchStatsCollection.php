<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6\Entity\SearchStats;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                   add(SearchStatsEntity $entity)
 * @method void                   set(string $key, SearchStatsEntity $entity)
 * @method SearchStatsEntity[]    getIterator()
 * @method SearchStatsEntity[]    getElements()
 * @method SearchStatsEntity|null get(string $key)
 * @method SearchStatsEntity|null first()
 * @method SearchStatsEntity|null last()
 */
class SearchStatsCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SearchStatsEntity::class;
    }
}
