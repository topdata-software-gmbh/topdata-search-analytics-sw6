---
filename: "_ai/backlog/active/260721_1934__IMPLEMENTATION_PLAN__extract_search_analytics_plugin.md"
title: "Extract Search Analytics into topdata-search-analytics-sw6"
createdAt: 2026-07-21 19:34
updatedAt: 2026-07-21 19:34
status: draft
priority: high
tags: [extraction, plugin, search-analytics, search-logging, elasticsearch-hacks, refactoring]
estimatedComplexity: moderate
documentType: IMPLEMENTATION_PLAN
---

# Extract Search Analytics into topdata-search-analytics-sw6

## 1. Problem Statement

The `topdata-elasticsearch-hacks-sw6` plugin currently bundles two unrelated concerns:
1. **Elasticsearch tokenization, synonyms, query boosting, and category search** — the "ES hacks" scope
2. **Search term logging, aggregation, and analytics** — the "Search Analytics" scope

The search analytics feature (~33 files, ~1,675 LOC) covers: raw search logging, session-stream consolidation, aggregated statistics, two admin modules, two API controllers, a CLI command, and a scheduled task. It has nothing to do with Elasticsearch internals and deserves its own plugin with a clean namespace, its own database tables, and its own admin navigation.

**No backward compatibility / data migration needed.** The existing `tdeh_*` tables will be left in place; the new plugin creates fresh `tdsa_*` tables.

---

## 2. Executive Summary

This plan extracts the full search analytics feature from `topdata-elasticsearch-hacks-sw6` into a new standalone plugin `topdata-search-analytics-sw6` with:

- **New namespace**: `Topdata\TopdataSearchAnalyticsSW6`
- **New table prefix**: `tdsa_` (SearchLog → `tdsa_search_log`, SearchStats → `tdsa_search_stats`)
- **New admin module namespace**: `topdata-sa-search-log` / `topdata-sa-search-stats` under a new nav parent
- **New CLI commands**: `topdata:search-analytics:consolidate-search-logs`
- **New scheduled task**: `topdata_search_analytics.consolidate_search_logs`
- **New API routes**: `/api/_action/topdata-search-analytics-sw6/search-stats/export|reset`
- **New admin snippets**: `TopdataSearchAnalyticsSW6.*` root namespace
- **CM interface placeholder**: An empty `SearchAnalyticsApiClient` service interface is defined (not implemented yet — future CM integration)
- **ZeroSearch table NOT extracted** — the legacy `tdeh_zero_search` table and its admin module are intentionally **not** moved. They are legacy and will be dropped from the old plugin in a follow-up cleanup.

The source files in `topdata-elasticsearch-hacks-sw6` are modified only to remove the extracted services and subscribers. All other ES-hacks functionality (synonyms, boosting, category search, etc.) remains untouched.

---

## 3. Project Environment

- **Project Name**: SW6.7 Plugin
- **Backend root**: `src`
- **PHP Version**: 8.2 / 8.3 / 8.4
- **Symfony**: 7.4
- **Shopware**: 6.7.*
- **Plugin Skeleton**: `/topdata/sw6-plugins/topdata-search-analytics-sw6/`

### Directory Layout (skeleton already exists)

```
topdata-search-analytics-sw6/
├── composer.json                    # exists (has plugin metadata)
├── src/
│   ├── TopdataSearchAnalyticsSW6.php  # exists (empty plugin class)
│   ├── Command/                     # exists (.gitkeep only)
│   ├── Controller/                  # exists (.gitkeep only)
│   ├── Service/                     # exists (.gitkeep only)
│   ├── Resources/
│   │   ├── config/
│   │   │   ├── services.xml         # exists (placeholder services)
│   │   │   ├── config.xml           # exists (placeholder)
│   │   │   ├── routes.xml           # exists (attribute route import)
│   │   │   └── plugin.png           # exists
│   │   ├── views/                   # exists (example twig)
│   │   └── app/
│   │       └── administration/      # does not exist yet
│   │           └── src/
│   │               └── main.ts
│   └── Migration/                   # does not exist yet
└── _ai/
    └── backlog/
        ├── active/
        └── reports/
```

### Conventions to Follow

- **PHP 8.2+ attributes** only (no annotations)
- **Constructor property promotion** everywhere
- **`#[AsCommand]`** for CLI commands, extends `AbstractTopdataCommand`
- **`CliLogger`** for all CLI output
- **`#[AutoconfigureTag('kernel.event_subscriber')]`** for event subscribers
- **`services.xml`** with autowiring; only explicit wiring for special repos
- **Flat snippet structure** under `src/Resources/app/administration/src/snippet/`
- **Snippet root namespace**: `TopdataSearchAnalyticsSW6` (PascalCase)
- **Admin module prefix**: `topdata-sa-` (short for "topdata search analytics")
- **Privilege**: `system.zero_search.viewer` (reuse existing ACL)
- **Table prefix**: `tdsa_`
- **CLI command prefix**: `topdata:search-analytics:`
- **API route prefix**: `/api/_action/topdata-search-analytics-sw6/`

---

## 4. Phase Overview

| Phase | Name | Files Created | Files Modified | Complexity |
|-------|------|--------------|----------------|------------|
| 1 | Plugin Bootstrap & Composer | 0 | 2 | trivial |
| 2 | Entity Layer | 6 | 0 | trivial |
| 3 | Database Migration | 1 | 0 | trivial |
| 4 | Core Service & Subscriber | 3 | 0 | simple |
| 5 | API Controllers | 1 | 0 | simple |
| 6 | CLI Command & Scheduled Task | 3 | 0 | simple |
| 7 | Plugin Class & Uninstall | 0 | 1 | trivial |
| 8 | Admin Modules (JS) | 6 | 0 | moderate |
| 9 | Snippets & Config | 3 | 1 | simple |
| 10 | services.xml Wiring | 0 | 1 | simple |
| 11 | Source Plugin Cleanup | 0 | 5 | simple |
| 12 | Housekeeping & Report | 2 | 1 | simple |
| **Total** | | **25** | **11** | |

---

## Phase 1: Plugin Bootstrap & Composer

### Task 1.1: Update `composer.json`

Update the existing `composer.json` to add the `shopware-plugin-class` and fix metadata.

**[MODIFY] `composer.json`**

```json
{
    "name":        "topdata/search-analytics-sw6",
    "description": "Topdata Search Analytics SW6 - Search term logging, aggregation and analytics",
    "version":     "v1.0.0",
    "type":        "shopware-platform-plugin",
    "license":     "MIT",
    "authors":     [
        {
            "name":     "TopData Software GmbH",
            "homepage": "https://www.topdata.de",
            "role":     "Manufacturer"
        }
    ],
    "require":     {
        "shopware/core": "6.7.*"
    },
    "extra":       {
        "shopware-plugin-class": "Topdata\\TopdataSearchAnalyticsSW6\\TopdataSearchAnalyticsSW6",
        "plugin-icon": "src/Resources/config/plugin.png",
        "copyright": "(c) by TopData Software GmbH",
        "label": {
            "en-GB": "Topdata Search Analytics SW6",
            "de-DE": "Topdata Search Analytics SW6"
        },
        "description": {
            "en-GB": "Search term logging, session consolidation, aggregated statistics and analytics for Shopware 6 storefront search",
            "de-DE": "Suchbegriff-Logging, Session-Konsolidierung, aggregierte Statistiken und Analysen für die Shopware 6 Storefront-Suche"
        },
        "manufacturerLink": {
            "de-DE": "https://www.topdata.de",
            "en-GB": "https://www.topdata.de"
        }
    },
    "autoload":    {
        "psr-4": {
            "Topdata\\TopdataSearchAnalyticsSW6\\": "src/"
        }
    }
}
```

### Task 1.2: Verify plugin class file

The existing `src/TopdataSearchAnalyticsSW6.php` is already correct (empty class extending `Plugin`).

**[MODIFY] `src/TopdataSearchAnalyticsSW6.php`**

```php
<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Doctrine\DBAL\Connection;

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
```

---

## Phase 2: Entity Layer

All entity classes follow the SW6.7 pattern: `EntityDefinition` + `Entity` + `Collection`.

### Task 2.1: SearchLog Entity

**[NEW FILE] `src/Entity/SearchLog/SearchLogEntityDefinition.php`**

```php
<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6\Entity\SearchLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class SearchLogEntityDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'tdsa_search_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SearchLogEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SearchLogCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('session_token', 'sessionToken'))->addFlags(new Required()),
            (new StringField('term', 'term'))->addFlags(new Required()),
            (new IntField('result_count', 'resultCount'))->addFlags(new Required()),
            (new DateTimeField('created_at', 'createdAt'))->addFlags(new Required()),
        ]);
    }
}
```

**[NEW FILE] `src/Entity/SearchLog/SearchLogEntity.php`**

```php
<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6\Entity\SearchLog;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class SearchLogEntity extends Entity
{
    use EntityIdTrait;

    protected string $sessionToken;
    protected string $term;
    protected int $resultCount;

    public function getSessionToken(): string
    {
        return $this->sessionToken;
    }

    public function setSessionToken(string $sessionToken): void
    {
        $this->sessionToken = $sessionToken;
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    public function setTerm(string $term): void
    {
        $this->term = $term;
    }

    public function getResultCount(): int
    {
        return $this->resultCount;
    }

    public function setResultCount(int $resultCount): void
    {
        $this->resultCount = $resultCount;
    }
}
```

**[NEW FILE] `src/Entity/SearchLog/SearchLogCollection.php`**

```php
<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6\Entity\SearchLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                add(SearchLogEntity $entity)
 * @method void                set(string $key, SearchLogEntity $entity)
 * @method SearchLogEntity[]   getIterator()
 * @method SearchLogEntity[]   getElements()
 * @method SearchLogEntity|null get(string $key)
 * @method SearchLogEntity|null first()
 * @method SearchLogEntity|null last()
 */
class SearchLogCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SearchLogEntity::class;
    }
}
```

### Task 2.2: SearchStats Entity

**[NEW FILE] `src/Entity/SearchStats/SearchStatsEntityDefinition.php`**

```php
<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6\Entity\SearchStats;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class SearchStatsEntityDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'tdsa_search_stats';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SearchStatsEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SearchStatsCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('term', 'term'))->addFlags(new Required()),
            (new IntField('count', 'count'))->addFlags(new Required()),
            (new IntField('zero_count', 'zeroCount'))->addFlags(new Required()),
            (new IntField('avg_result_count', 'avgResultCount'))->addFlags(new Required()),
            (new DateTimeField('created_at', 'createdAt'))->addFlags(new Required()),
            (new DateTimeField('last_searched_at', 'lastSearchedAt')),
        ]);
    }
}
```

**[NEW FILE] `src/Entity/SearchStats/SearchStatsEntity.php`**

```php
<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6\Entity\SearchStats;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class SearchStatsEntity extends Entity
{
    use EntityIdTrait;

    protected string $term;
    protected int $count;
    protected int $zeroCount;
    protected int $avgResultCount;
    protected ?\DateTimeInterface $lastSearchedAt = null;

    public function getTerm(): string
    {
        return $this->term;
    }

    public function setTerm(string $term): void
    {
        $this->term = $term;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): void
    {
        $this->count = $count;
    }

    public function getZeroCount(): int
    {
        return $this->zeroCount;
    }

    public function setZeroCount(int $zeroCount): void
    {
        $this->zeroCount = $zeroCount;
    }

    public function getAvgResultCount(): int
    {
        return $this->avgResultCount;
    }

    public function setAvgResultCount(int $avgResultCount): void
    {
        $this->avgResultCount = $avgResultCount;
    }

    public function getLastSearchedAt(): ?\DateTimeInterface
    {
        return $this->lastSearchedAt;
    }

    public function setLastSearchedAt(?\DateTimeInterface $lastSearchedAt): void
    {
        $this->lastSearchedAt = $lastSearchedAt;
    }
}
```

**[NEW FILE] `src/Entity/SearchStats/SearchStatsCollection.php`**

```php
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
```

---

## Phase 3: Database Migration

Create both tables in a single migration. Timestamp uses current epoch (mid-July 2026 range).

**[NEW FILE] `src/Migration/Migration1753122000CreateSearchLogAndStatsTable.php`**

```php
<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1753122000CreateSearchLogAndStatsTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1753122000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `tdsa_search_log` (
                `id` BINARY(16) NOT NULL,
                `session_token` VARCHAR(255) NOT NULL,
                `term` VARCHAR(255) NOT NULL,
                `result_count` INT NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.tdsa_search_log.session_token` (`session_token`),
                INDEX `idx.tdsa_search_log.created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `tdsa_search_stats` (
                `id` BINARY(16) NOT NULL,
                `term` VARCHAR(255) NOT NULL,
                `count` INT NOT NULL DEFAULT 1,
                `zero_count` INT NOT NULL DEFAULT 0,
                `avg_result_count` INT NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `last_searched_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.tdsa_search_stats.term` (`term`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
```

> **Note**: The migration timestamp `1753122000` corresponds to approximately `2026-07-21 14:00:00 UTC`. Adjust to current epoch at implementation time.

---

## Phase 4: Core Service & Subscriber

### Task 4.1: SearchAnalyticsService

This is the consolidation engine — extracted from `topdata-elasticsearch-hacks-sw6` with table names changed from `tdeh_` to `tdsa_`.

**[NEW FILE] `src/Service/SearchAnalyticsService.php`**

```php
<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

class SearchAnalyticsService
{
    private const TIME_THRESHOLD = 15;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function consolidate(int $batchSize = 100): int
    {
        $safetyMargin = (new \DateTime())->modify('-1 minute')->format('Y-m-d H:i:s.v');

        $tokens = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT `session_token`
             FROM `tdsa_search_log`
             WHERE `created_at` < :margin
             LIMIT :limit',
            ['margin' => $safetyMargin, 'limit' => $batchSize],
            ['margin' => \PDO::PARAM_STR, 'limit' => \PDO::PARAM_INT]
        );

        if (empty($tokens)) {
            return 0;
        }

        $processedCount = 0;

        foreach ($tokens as $token) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT `id`, `term`, `result_count`, `created_at`
                 FROM `tdsa_search_log`
                 WHERE `session_token` = :token AND `created_at` < :margin
                 ORDER BY `created_at` ASC',
                ['token' => $token, 'margin' => $safetyMargin]
            );

            if (empty($rows)) {
                continue;
            }

            $streams = $this->groupIntoStreams($rows);
            $finalIntents = [];

            foreach ($streams as $stream) {
                $finalIntents[] = $this->resolveStreamToIntent($stream);
            }

            $this->connection->beginTransaction();
            try {
                foreach ($finalIntents as $intent) {
                    $this->upsertStat(
                        $intent['term'],
                        $intent['result_count'],
                        $intent['created_at']
                    );
                }

                $ids = array_column($rows, 'id');
                $this->connection->executeStatement(
                    'DELETE FROM `tdsa_search_log` WHERE `id` IN (:ids)',
                    ['ids' => $ids],
                    ['ids' => Connection::PARAM_BINARY_ARRAY]
                );

                $this->connection->commit();
                $processedCount += count($rows);
            } catch (\Throwable $e) {
                $this->connection->rollBack();
                throw $e;
            }
        }

        return $processedCount;
    }

    private function groupIntoStreams(array $rows): array
    {
        $streams = [];
        $currentStream = [];

        foreach ($rows as $row) {
            if (empty($currentStream)) {
                $currentStream[] = $row;
                continue;
            }

            $prev = end($currentStream);
            $timeDiff = (new \DateTime($row['created_at']))->getTimestamp() - (new \DateTime($prev['created_at']))->getTimestamp();

            if ($timeDiff <= self::TIME_THRESHOLD && $this->isRelated($prev['term'], $row['term'])) {
                $currentStream[] = $row;
            } else {
                $streams[] = $currentStream;
                $currentStream = [$row];
            }
        }

        if (!empty($currentStream)) {
            $streams[] = $currentStream;
        }

        return $streams;
    }

    private function isRelated(string $termA, string $termB): bool
    {
        if (str_starts_with($termB, $termA)) {
            return true;
        }

        if (str_starts_with($termA, $termB)) {
            return true;
        }

        $levenshtein = levenshtein($termA, $termB);
        $maxLength = max(strlen($termA), strlen($termB));
        $maxAllowedEdits = $maxLength > 6 ? 3 : 2;

        return $levenshtein <= $maxAllowedEdits;
    }

    private function resolveStreamToIntent(array $stream): array
    {
        $last = end($stream);

        if ($last['result_count'] > 0) {
            return $last;
        }

        foreach (array_reverse($stream) as $entry) {
            if ($entry['result_count'] > 0) {
                return $entry;
            }
        }

        return $last;
    }

    private function upsertStat(string $term, int $resultCount, string $createdAt): void
    {
        $isZero = $resultCount === 0 ? 1 : 0;

        $this->connection->executeStatement(
            'INSERT INTO `tdsa_search_stats` (`id`, `term`, `count`, `zero_count`, `avg_result_count`, `created_at`, `last_searched_at`)
             VALUES (:id, :term, 1, :is_zero, :result_count, :now, :now)
             ON DUPLICATE KEY UPDATE
                `count` = `count` + 1,
                `zero_count` = `zero_count` + :is_zero,
                `avg_result_count` = ROUND((`avg_result_count` * `count` + :result_count) / (`count` + 1)),
                `last_searched_at` = :now',
            [
                'id' => Uuid::randomBytes(),
                'term' => $term,
                'is_zero' => $isZero,
                'result_count' => $resultCount,
                'now' => $createdAt
            ]
        );
    }
}
```

### Task 4.2: SearchAnalyticsApiClientInterface (CM placeholder)

Define the interface now so the architecture is ready when the CM integration is built.

**[NEW FILE] `src/Service/SearchAnalyticsApiClientInterface.php`**

```php
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
```

### Task 4.3: ProductSearchSubscriber

**[NEW FILE] `src/Subscriber/ProductSearchSubscriber.php`**

```php
<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestResultEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSearchSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_SEARCH_RESULT => 'onSearchResult',
            ProductEvents::PRODUCT_SUGGEST_RESULT => 'onSearchResult',
        ];
    }

    public function onSearchResult($event): void
    {
        $result = $event->getResult();
        $term = $result->getCriteria()->getTerm();

        if ($term === null || trim($term) === '') {
            return;
        }

        $term = mb_strtolower(trim($term));
        if (mb_strlen($term) > 255) {
            $term = mb_substr($term, 0, 255);
        }

        if (mb_strlen($term) < 2) {
            return;
        }

        $resultCount = $result->getTotal();
        $sessionToken = $event->getSalesChannelContext()->getToken();

        try {
            $this->connection->executeStatement(
                'INSERT INTO `tdsa_search_log` (`id`, `session_token`, `term`, `result_count`, `created_at`)
                 VALUES (:id, :session_token, :term, :result_count, :now)',
                [
                    'id' => Uuid::randomBytes(),
                    'session_token' => $sessionToken,
                    'term' => $term,
                    'result_count' => $resultCount,
                    'now' => (new \DateTime())->format('Y-m-d H:i:s.v')
                ]
            );
        } catch (\Throwable $e) {
        }
    }
}
```

---

## Phase 5: API Controllers

### Task 5.1: SearchStatsController

**[NEW FILE] `src/Controller/SearchStatsController.php`**

```php
<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class SearchStatsController extends AbstractController
{
    public function __construct(private readonly Connection $connection)
    {
    }

    #[Route(
        path: '/api/_action/topdata-search-analytics-sw6/search-stats/export',
        name: 'api.action.searchanalyticssw6.search-stats.export',
        methods: ['GET']
    )]
    public function exportAction(): Response
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT term, count, zero_count, avg_result_count, created_at, last_searched_at
             FROM tdsa_search_stats
             ORDER BY count DESC'
        );

        $csv = "\xEF\xBB\xBF";
        $csv .= '"term","total_searches","zero_result_searches","avg_result_count","created_at","last_searched_at"' . "\n";

        foreach ($rows as $row) {
            $csv .= sprintf(
                '"%s",%d,%d,%d,"%s","%s"' . "\n",
                str_replace('"', '""', $row['term']),
                (int)$row['count'],
                (int)$row['zero_count'],
                (int)$row['avg_result_count'],
                $row['created_at'] ?? '',
                $row['last_searched_at'] ?? ''
            );
        }

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="search-statistics.csv"',
        ]);
    }

    #[Route(
        path: '/api/_action/topdata-search-analytics-sw6/search-stats/reset',
        name: 'api.action.searchanalyticssw6.search-stats.reset',
        methods: ['POST']
    )]
    public function resetAction(): JsonResponse
    {
        $this->connection->executeStatement('TRUNCATE TABLE `tdsa_search_stats`');
        $this->connection->executeStatement('TRUNCATE TABLE `tdsa_search_log`');

        return new JsonResponse(['success' => true]);
    }
}
```

> **Note**: The old `ZeroSearchController` is intentionally **not** extracted. The legacy `tdeh_zero_search` table will be removed from the old plugin in a separate cleanup phase.

---

## Phase 6: CLI Command & Scheduled Task

### Task 6.1: Consolidate Command

**[NEW FILE] `src/Command/Command_ConsolidateSearchLogs.php`**

```php
<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Topdata\TopdataFoundationSW6\Command\AbstractTopdataCommand;
use Topdata\TopdataFoundationSW6\Util\CliLogger;
use Topdata\TopdataSearchAnalyticsSW6\Service\SearchAnalyticsService;

#[AsCommand(
    name: 'topdata:search-analytics:consolidate-search-logs',
    description: 'Consolidates raw search logs into aggregated analytics'
)]
class Command_ConsolidateSearchLogs extends AbstractTopdataCommand
{
    public function __construct(private readonly SearchAnalyticsService $analyticsService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Max session tokens to process in one run', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $batchSize = (int) $input->getOption('batch-size');

        CliLogger::info(sprintf('Starting search log consolidation with batch size %d...', $batchSize));

        try {
            $processed = $this->analyticsService->consolidate($batchSize);
            CliLogger::success(sprintf('Consolidation finished. Consolidated %d raw search event(s).', $processed));
        } catch (\Throwable $e) {
            CliLogger::error(sprintf('Consolidation failed: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
```

### Task 6.2: Scheduled Task

**[NEW FILE] `src/Framework/ScheduledTask/ConsolidateSearchLogsTask.php`**

```php
<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6\Framework\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ConsolidateSearchLogsTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'topdata_search_analytics.consolidate_search_logs';
    }

    public static function getDefaultInterval(): int
    {
        return 3600;
    }
}
```

**[NEW FILE] `src/Framework/ScheduledTask/ConsolidateSearchLogsTaskHandler.php`**

```php
<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6\Framework\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Topdata\TopdataSearchAnalyticsSW6\Service\SearchAnalyticsService;

#[AsMessageHandler(handles: ConsolidateSearchLogsTask::class)]
class ConsolidateSearchLogsTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        $scheduledTaskRepository,
        private readonly SearchAnalyticsService $analyticsService
    ) {
        parent::__construct($scheduledTaskRepository);
    }

    public function run(): void
    {
        try {
            $this->analyticsService->consolidate(250);
        } catch (\Throwable $e) {
        }
    }
}
```

---

## Phase 7: Plugin Class

Already covered in Phase 1, Task 1.2. The `uninstall()` method drops `tdsa_search_log` and `tdsa_search_stats` when `keepUserData` is false.

---

## Phase 8: Admin Modules (JS)

### Task 8.1: Admin Entry Point

**[NEW FILE] `src/Resources/app/administration/src/main.ts`**

```typescript
import './module/topdata-sa-search-stats';
import './module/topdata-sa-search-log';
```

### Task 8.2: Search Stats Module

**[NEW FILE] `src/Resources/app/administration/src/module/topdata-sa-search-stats/index.ts`**

```typescript
import './page/search-stats-list';

Shopware.Module.register('topdata-sa-search-stats', {
    type: 'plugin',
    name: 'SearchStats',
    title: 'TopdataSearchAnalyticsSW6.topdata-sa-search-stats.title',
    description: 'TopdataSearchAnalyticsSW6.topdata-sa-search-stats.description',
    color: '#189eff',
    icon: 'default-shopping-search',

    routes: {
        list: {
            component: 'topdata-sa-search-stats-list',
            path: 'list',
            meta: {
                privilege: 'system.zero_search.viewer',
            },
        },
    },

    navigation: [{
        id: 'topdata-search-analytics-sw6',
        label: 'TopdataSearchAnalyticsSW6.nav.mainTitle',
        color: '#189eff',
        icon: 'default-shopping-search',
        position: 100,
        parent: 'sw-content',
    }, {
        id: 'topdata-sa-search-stats-list',
        label: 'TopdataSearchAnalyticsSW6.nav.searchStats',
        color: '#189eff',
        path: 'topdata.sa.search.stats.list',
        parent: 'topdata-search-analytics-sw6',
    }],
});
```

**[NEW FILE] `src/Resources/app/administration/src/module/topdata-sa-search-stats/page/search-stats-list/index.ts`**

```typescript
import template from './search-stats-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('topdata-sa-search-stats-list', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            items: null,
            isLoading: true,
            sortBy: 'count',
            sortDirection: 'DESC',
            limit: 25,
            showResetModal: false,
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('tdsa_search_stats');
        },

        columns() {
            return [{
                property: 'term',
                label: this.$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-stats.columnTerm'),
                allowResize: true,
                primary: true,
            }, {
                property: 'count',
                label: this.$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-stats.columnCount'),
                allowResize: true,
                sortable: true,
            }, {
                property: 'zeroCount',
                label: this.$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-stats.columnZeroCount'),
                allowResize: true,
                sortable: true,
            }, {
                property: 'avgResultCount',
                label: this.$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-stats.columnAvgResultCount'),
                allowResize: true,
                sortable: true,
            }, {
                property: 'lastSearchedAt',
                label: this.$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-stats.columnLastSearchedAt'),
                allowResize: true,
                sortable: true,
            }, {
                property: 'createdAt',
                label: this.$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-stats.columnCreatedAt'),
                allowResize: true,
                sortable: true,
            }];
        },
    },

    mounted() {
        this.getList();
    },

    methods: {
        getList() {
            this.isLoading = true;

            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            this.repository.search(criteria).then((result) => {
                this.total = result.total;
                this.items = result;
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onPageChange(params) {
            this.page = params.page;
            this.limit = params.limit;
            this.getList();
        },

        onSortColumn(column) {
            this.sortBy = column.dataIndex ?? column.property;
            this.sortDirection = column.sortDirection ?? 'ASC';
            this.getList();
        },

        onDownloadCsv() {
            const httpClient = Shopware.Application.getContainer('init').httpClient;
            httpClient.get('_action/topdata-search-analytics-sw6/search-stats/export', {
                responseType: 'blob',
            }).then((response) => {
                const url = window.URL.createObjectURL(response.data);
                const link = document.createElement('a');
                link.setAttribute('href', url);
                link.setAttribute('download', 'search-statistics.csv');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-stats.exportError'),
                });
            });
        },

        onReset() {
            this.showResetModal = true;
        },

        onConfirmReset() {
            this.showResetModal = false;
            this.isLoading = true;

            const httpClient = Shopware.Application.getContainer('init').httpClient;
            httpClient.post('_action/topdata-search-analytics-sw6/search-stats/reset', {})
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-stats.resetSuccess'),
                    });
                    this.getList();
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-stats.resetError'),
                    });
                    this.isLoading = false;
                });
        },

        onCancelReset() {
            this.showResetModal = false;
        },
    },
});
```

**[NEW FILE] `src/Resources/app/administration/src/module/topdata-sa-search-stats/page/search-stats-list/search-stats-list.html.twig`**

```twig
<sw-page class="topdata-sa-search-stats-list-page">
    <template #smart-bar-header>
        <h2>{{ $tc('TopdataSearchAnalyticsSW6.topdata-sa-search-stats.title') }}</h2>
    </template>

    <template #smart-bar-actions>
        <sw-button variant="primary" @click="onDownloadCsv">
            {{ $tc('TopdataSearchAnalyticsSW6.topdata-sa-search-stats.buttonDownloadCsv') }}
        </sw-button>
        <sw-button variant="danger" @click="onReset">
            {{ $tc('TopdataSearchAnalyticsSW6.topdata-sa-search-stats.buttonReset') }}
        </sw-button>
    </template>

    <template #content>
        <sw-entity-listing
            v-if="items"
            :dataSource="items"
            :columns="columns"
            :repository="repository"
            identifier="topdata-sa-search-stats"
            :show-settings="true"
            :show-selection="false"
            :allow-view="false"
            :allow-edit="false"
            :allow-delete="true"
            :allow-inline-edit="false"
            :full-page="true"
            :sort-by="sortBy"
            :sort-direction="sortDirection"
            :is-loading="isLoading"
            @page-change="onPageChange"
            @column-sort="onSortColumn"
        >
            <template #column-lastSearchedAt="{ item }">
                <sw-time-ago v-if="item.lastSearchedAt" :date="item.lastSearchedAt" />
            </template>

            <template #column-createdAt="{ item }">
                <sw-time-ago v-if="item.createdAt" :date="item.createdAt" />
            </template>
        </sw-entity-listing>

        <sw-modal
            v-if="showResetModal"
            :title="$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-stats.resetModalTitle')"
            variant="small"
            @modal-close="onCancelReset"
        >
            <p>{{ $tc('TopdataSearchAnalyticsSW6.topdata-sa-search-stats.resetModalBody') }}</p>

            <template #modal-footer>
                <sw-button size="small" @click="onCancelReset">
                    {{ $tc('global.default.cancel') }}
                </sw-button>
                <sw-button variant="danger" size="small" @click="onConfirmReset">
                    {{ $tc('TopdataSearchAnalyticsSW6.topdata-sa-search-stats.resetModalConfirm') }}
                </sw-button>
            </template>
        </sw-modal>
    </template>
</sw-page>
```

### Task 8.3: Search Log Module

**[NEW FILE] `src/Resources/app/administration/src/module/topdata-sa-search-log/index.ts`**

```typescript
import './page/search-log-list';

Shopware.Module.register('topdata-sa-search-log', {
    type: 'plugin',
    name: 'SearchLog',
    title: 'TopdataSearchAnalyticsSW6.topdata-sa-search-log.title',
    description: 'TopdataSearchAnalyticsSW6.topdata-sa-search-log.description',
    color: '#189eff',
    icon: 'default-shopping-search',

    routes: {
        list: {
            component: 'topdata-sa-search-log-list',
            path: 'list',
            meta: {
                privilege: 'system.zero_search.viewer',
            },
        },
    },

    navigation: [{
        id: 'topdata-search-analytics-sw6',
        label: 'TopdataSearchAnalyticsSW6.nav.mainTitle',
        color: '#189eff',
        icon: 'default-shopping-search',
        position: 100,
        parent: 'sw-content',
    }, {
        id: 'topdata-sa-search-log-list',
        label: 'TopdataSearchAnalyticsSW6.nav.searchLog',
        color: '#189eff',
        path: 'topdata.sa.search.log.list',
        parent: 'topdata-search-analytics-sw6',
    }],
});
```

**[NEW FILE] `src/Resources/app/administration/src/module/topdata-sa-search-log/page/search-log-list/index.ts`**

```typescript
import template from './search-log-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('topdata-sa-search-log-list', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            items: null,
            isLoading: true,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            limit: 25,
            termFilter: null,
        };
    },

    computed: {
        repository() {
            return this.repositoryFactory.create('tdsa_search_log');
        },

        columns() {
            return [{
                property: 'term',
                label: this.$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-log.columnTerm'),
                allowResize: true,
                primary: true,
            }, {
                property: 'resultCount',
                label: this.$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-log.columnResultCount'),
                allowResize: true,
                sortable: true,
            }, {
                property: 'sessionToken',
                label: this.$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-log.columnSessionToken'),
                allowResize: true,
            }, {
                property: 'createdAt',
                label: this.$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-log.columnCreatedAt'),
                allowResize: true,
                sortable: true,
            }];
        },
    },

    mounted() {
        this.getList();
    },

    methods: {
        getList() {
            this.isLoading = true;

            const criteria = new Criteria(this.page, this.limit);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));

            if (this.termFilter) {
                criteria.addFilter(Criteria.contains('term', this.termFilter));
            }

            this.repository.search(criteria).then((result) => {
                this.total = result.total;
                this.items = result;
                this.isLoading = false;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onPageChange(params) {
            this.page = params.page;
            this.limit = params.limit;
            this.getList();
        },

        onSortColumn(column) {
            this.sortBy = column.dataIndex ?? column.property;
            this.sortDirection = column.sortDirection ?? 'ASC';
            this.getList();
        },

        onRefresh() {
            this.getList();
        },

        onSearchTerm() {
            this.page = 1;
            this.getList();
        },
    },
});
```

**[NEW FILE] `src/Resources/app/administration/src/module/topdata-sa-search-log/page/search-log-list/search-log-list.html.twig`**

```twig
<sw-page class="topdata-sa-search-log-list-page">
    <template #smart-bar-header>
        <h2>{{ $tc('TopdataSearchAnalyticsSW6.topdata-sa-search-log.title') }}</h2>
    </template>

    <template #smart-bar-actions>
        <sw-button variant="primary" @click="onRefresh">
            {{ $tc('TopdataSearchAnalyticsSW6.topdata-sa-search-log.buttonRefresh') }}
        </sw-button>
    </template>

    <template #search-bar>
        <sw-search-bar
            :placeholder="$tc('TopdataSearchAnalyticsSW6.topdata-sa-search-log.searchPlaceholder')"
            :initial-search="termFilter"
            @search="onSearchTerm"
        />
    </template>

    <template #content>
        <sw-alert variant="info" appearance="notification" :show-icon="true">
            {{ $tc('TopdataSearchAnalyticsSW6.topdata-sa-search-log.transientNotice') }}
        </sw-alert>

        <sw-entity-listing
            v-if="items"
            :dataSource="items"
            :columns="columns"
            :repository="repository"
            identifier="topdata-sa-search-log"
            :show-settings="true"
            :show-selection="false"
            :allow-view="false"
            :allow-edit="false"
            :allow-delete="false"
            :allow-inline-edit="false"
            :full-page="true"
            :sort-by="sortBy"
            :sort-direction="sortDirection"
            :is-loading="isLoading"
            @page-change="onPageChange"
            @column-sort="onSortColumn"
        >
            <template #column-createdAt="{ item }">
                <sw-time-ago :date="item.createdAt" />
            </template>

            <template #column-sessionToken="{ item }">
                <code style="font-size: 0.85em; word-break: break-all;">{{ item.sessionToken }}</code>
            </template>
        </sw-entity-listing>
    </template>
</sw-page>
```

---

## Phase 9: Snippets & Config

### Task 9.1: English Snippets

**[NEW FILE] `src/Resources/app/administration/src/snippet/en-GB.json`**

```json
{
    "TopdataSearchAnalyticsSW6": {
        "nav": {
            "mainTitle": "Search Analytics",
            "searchStats": "Search Statistics",
            "searchLog": "Search Log"
        },
        "topdata-sa-search-stats": {
            "title": "Search Statistics",
            "description": "Detailed customer search statistics",
            "listTitle": "Search Terms",
            "columnTerm": "Search Term",
            "columnCount": "Total Searches",
            "columnZeroCount": "Zero Result Searches",
            "columnAvgResultCount": "ø Hit Count",
            "columnLastSearchedAt": "Last Searched",
            "columnCreatedAt": "First Seen",
            "buttonDownloadCsv": "Download CSV",
            "buttonReset": "Reset",
            "resetModalTitle": "Reset Search Statistics",
            "resetModalBody": "Are you sure you want to delete all search statistics and pending search logs? This action cannot be undone.",
            "resetModalConfirm": "Yes, reset all",
            "resetSuccess": "Search statistics have been reset.",
            "resetError": "Failed to reset search statistics.",
            "exportError": "Failed to export search statistics."
        },
        "topdata-sa-search-log": {
            "title": "Search Log",
            "description": "Live view of raw search queries",
            "columnTerm": "Search Term",
            "columnResultCount": "Results",
            "columnSessionToken": "Session",
            "columnCreatedAt": "Searched At",
            "buttonRefresh": "Refresh",
            "searchPlaceholder": "Filter by search term…",
            "transientNotice": "This log is transient. Raw search queries are automatically consolidated and purged hourly by the background task. Data shown here is a live snapshot and may disappear after the next consolidation run."
        }
    }
}
```

### Task 9.2: German Snippets

**[NEW FILE] `src/Resources/app/administration/src/snippet/de-DE.json`**

```json
{
    "TopdataSearchAnalyticsSW6": {
        "nav": {
            "mainTitle": "Suchanalysen",
            "searchStats": "Suchstatistiken",
            "searchLog": "Suchprotokoll"
        },
        "topdata-sa-search-stats": {
            "title": "Suchstatistiken",
            "description": "Statistiken über Suchanfragen von Kunden",
            "listTitle": "Suchbegriffe",
            "columnTerm": "Suchbegriff",
            "columnCount": "Suchanfragen gesamt",
            "columnZeroCount": "Null-Ergebnisse gesamt",
            "columnAvgResultCount": "ø Trefferanzahl",
            "columnLastSearchedAt": "Zuletzt gesucht",
            "columnCreatedAt": "Erstmals gesehen",
            "buttonDownloadCsv": "CSV herunterladen",
            "buttonReset": "Zurücksetzen",
            "resetModalTitle": "Statistiken zurücksetzen",
            "resetModalBody": "Sind Sie sicher, dass alle Suchstatistiken und unvollständigen Suchprotokolle gelöscht werden sollen? Diese Aktion kann nicht rückgängig gemacht werden.",
            "resetModalConfirm": "Ja, alle zurücksetzen",
            "resetSuccess": "Suchstatistiken wurden zurückgesetzt.",
            "resetError": "Fehler beim Zurücksetzen der Suchstatistiken.",
            "exportError": "Fehler beim Exportieren der Suchstatistiken."
        },
        "topdata-sa-search-log": {
            "title": "Suchprotokoll",
            "description": "Live-Ansicht der rohen Suchanfragen",
            "columnTerm": "Suchbegriff",
            "columnResultCount": "Ergebnisse",
            "columnSessionToken": "Sitzung",
            "columnCreatedAt": "Gesucht am",
            "buttonRefresh": "Aktualisieren",
            "searchPlaceholder": "Nach Suchbegriff filtern…",
            "transientNotice": "Dieses Protokoll ist transient. Rohe Suchanfragen werden stündlich automatisch konsolidiert und gelöscht. Die angezeigten Daten sind eine Live-Momentaufnahme und können nach der nächsten Konsolidierung verschwinden."
        }
    }
}
```

### Task 9.3: Plugin Config

Replace the placeholder `config.xml` with the real configuration.

**[MODIFY] `src/Resources/config/config.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/platform/trunk/src/Core/System/SystemConfig/Schema/config.xsd">
    <card>
        <title>Search Analytics</title>
        <title lang="de-DE">Suchanalysen</title>

        <input-field type="bool">
            <name>enabled</name>
            <label>Enable Search Logging</label>
            <label lang="de-DE">Suchbegriff-Logging aktivieren</label>
            <defaultValue>true</defaultValue>
        </input-field>
    </card>
</config>
```

> **Note**: The `enabled` config flag is not yet wired into the subscriber. It serves as a placeholder for future conditional logging. The subscriber currently logs unconditionally. Future iterations can inject `SystemConfigService` into the subscriber and check this flag.

---

## Phase 10: services.xml Wiring

**[MODIFY] `src/Resources/config/services.xml`**

```xml
<?xml version="1.0" ?>
<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <!-- Entity Definitions -->
        <service id="Topdata\TopdataSearchAnalyticsSW6\Entity\SearchLog\SearchLogEntityDefinition">
            <tag name="shopware.entity.definition"/>
        </service>

        <service id="Topdata\TopdataSearchAnalyticsSW6\Entity\SearchStats\SearchStatsEntityDefinition">
            <tag name="shopware.entity.definition"/>
        </service>

        <!-- Core Business Logic -->
        <service id="Topdata\TopdataSearchAnalyticsSW6\Service\SearchAnalyticsService" public="true">
            <argument type="service" id="Doctrine\DBAL\Connection" key="$connection"/>
        </service>

        <!-- Storefront Search Logging Subscriber -->
        <service id="Topdata\TopdataSearchAnalyticsSW6\Subscriber\ProductSearchSubscriber">
            <argument type="service" id="Doctrine\DBAL\Connection" key="$connection"/>
            <tag name="kernel.event_subscriber"/>
        </service>

        <!-- CLI Commands -->
        <service id="Topdata\TopdataSearchAnalyticsSW6\Command\Command_ConsolidateSearchLogs">
            <argument type="service" id="Topdata\TopdataSearchAnalyticsSW6\Service\SearchAnalyticsService" key="$analyticsService"/>
            <tag name="console.command"/>
        </service>

        <!-- Scheduled Tasks -->
        <service id="Topdata\TopdataSearchAnalyticsSW6\Framework\ScheduledTask\ConsolidateSearchLogsTask">
            <tag name="shopware.scheduled.task"/>
        </service>

        <service id="Topdata\TopdataSearchAnalyticsSW6\Framework\ScheduledTask\ConsolidateSearchLogsTaskHandler">
            <argument type="service" id="scheduled_task.repository"/>
            <argument type="service" id="Topdata\TopdataSearchAnalyticsSW6\Service\SearchAnalyticsService" key="$analyticsService"/>
            <tag name="messenger.message_handler"/>
        </service>

        <!-- API Controllers -->
        <service id="Topdata\TopdataSearchAnalyticsSW6\Controller\SearchStatsController" public="true">
            <argument type="service" id="Doctrine\DBAL\Connection" key="$connection"/>
            <call method="setContainer">
                <argument type="service" id="service_container"/>
            </call>
        </service>
    </services>
</container>
```

> **Cleanup**: Delete the example controllers `src/Controller/StorefrontExampleController.php`, `src/Controller/AdminApiExampleController.php`, `src/Command/ExampleCommand.php`, and `src/Resources/views/storefront/example.html.twig` from the skeleton.

---

## Phase 11: Source Plugin Cleanup (topdata-elasticsearch-hacks-sw6)

After the new plugin is verified to work, remove the extracted services from the old plugin. This phase requires modifying the old plugin.

### Task 11.1: Remove ProductSearchSubscriber

**[DELETE] `topdata-elasticsearch-hacks-sw6/src/Subscriber/ProductSearchSubscriber.php`**

Remove this file entirely. The subscriber writes to `tdeh_search_log` which is superseded by the new plugin's `tdsa_search_log`.

### Task 11.2: Remove SearchAnalyticsService

**[DELETE] `topdata-elasticsearch-hacks-sw6/src/Service/SearchAnalyticsService.php`**

Superseded by `Topdata\TopdataSearchAnalyticsSW6\Service\SearchAnalyticsService`.

### Task 11.3: Remove Search Log/Stats Entities

**[DELETE] `topdata-elasticsearch-hacks-sw6/src/Entity/SearchLog/`** (3 files: Entity, EntityDefinition, Collection)

**[DELETE] `topdata-elasticsearch-hacks-sw6/src/Entity/SearchStats/`** (3 files: Entity, EntityDefinition, Collection)

### Task 11.4: Remove Controllers

**[DELETE] `topdata-elasticsearch-hacks-sw6/src/Controller/ZeroSearchController.php`**

**[DELETE] `topdata-elasticsearch-hacks-sw6/src/Controller/SearchStatsController.php`**

### Task 11.5: Remove Commands

**[DELETE] `topdata-elasticsearch-hacks-sw6/src/Command/Command_ConsolidateSearchLogs.php`**

### Task 11.6: Remove Scheduled Tasks

**[DELETE] `topdata-elasticsearch-hacks-sw6/src/Framework/ScheduledTask/ConsolidateSearchLogsTask.php`**

**[DELETE] `topdata-elasticsearch-hacks-sw6/src/Framework/ScheduledTask/ConsolidateSearchLogsTaskHandler.php`**

### Task 11.7: Update services.xml

**[MODIFY] `topdata-elasticsearch-hacks-sw6/src/Resources/config/services.xml`**

Remove these service definitions:
- `Topdata\TopdataElasticsearchHacksSW6\Entity\SearchLog\SearchLogEntityDefinition`
- `Topdata\TopdataElasticsearchHacksSW6\Entity\SearchStats\SearchStatsEntityDefinition`
- `Topdata\TopdataElasticsearchHacksSW6\Service\SearchAnalyticsService`
- `Topdata\TopdataElasticsearchHacksSW6\Subscriber\ProductSearchSubscriber`
- `Topdata\TopdataElasticsearchHacksSW6\Command\Command_ConsolidateSearchLogs`
- `Topdata\TopdataElasticsearchHacksSW6\Framework\ScheduledTask\ConsolidateSearchLogsTask`
- `Topdata\TopdataElasticsearchHacksSW6\Framework\ScheduledTask\ConsolidateSearchLogsTaskHandler`
- `Topdata\TopdataElasticsearchHacksSW6\Controller\ZeroSearchController`
- `Topdata\TopdataElasticsearchHacksSW6\Controller\SearchStatsController`

### Task 11.8: Update main.ts

**[MODIFY] `topdata-elasticsearch-hacks-sw6/src/Resources/app/administration/src/main.ts`**

Remove imports:
```typescript
import './module/topdata-es-search-stats';
import './module/topdata-es-search-log';
import './module/topdata-es-zero-search';
```

Keep: `import './module/topdata-es-synonym';`

### Task 11.9: Update Plugin uninstall()

**[MODIFY] `topdata-elasticsearch-hacks-sw6/src/TopdataElasticsearchHacksSW6.php`**

Remove `'tdeh_search_log'` and `'tdeh_search_stats'` from the `$tables` array in `uninstall()`. Keep `tdeh_zero_search` and other tables.

---

## Phase 12: Housekeeping & Report

### Task 12.1: Update `.gitignore`

**[MODIFY] `.gitignore`**

```
# Ignore compiled administration and storefront build outputs
src/Resources/public/administration/
src/Resources/public/storefront/

src/Resources/app/storefront/dist/
vendor/
node_modules/
.idea/
*.log

# Keep .gitkeep files
!/**/.gitkeep
```

> No changes needed — the existing `.gitignore` already covers all build artifacts.

### Task 12.2: Update README.md

**[MODIFY] `README.md`**

```markdown
# Topdata Search Analytics SW6

![Plugin Icon](src/Resources/config/plugin.png)

Shopware 6 plugin that logs, consolidates and analyzes storefront search queries.

## Features

- **Raw Search Log** (`tdsa_search_log`): Every storefront search is logged with term, result count, session token and timestamp
- **Session Consolidation**: Raw logs are grouped into search streams by session and intent (typo correction, prefix expansion)
- **Aggregated Statistics** (`tdsa_search_stats`): Per-term statistics with total searches, zero-result count, average hit count
- **Admin Modules**: Search Log viewer + Search Statistics with CSV export
- **Scheduled Task**: Hourly automatic consolidation of raw logs into stats

## Installation

1. Download the plugin
2. Upload to your Shopware 6 installation
3. Install and activate the plugin
4. Run `php bin/console database:migrate TopdataSearchAnalyticsSW6 --all`
5. Run `php bin/console cache:clear`

## Requirements

- Shopware 6.7.*
- PHP 8.2+

## Console Commands

| Command | Description |
|---------|-------------|
| `topdata:search-analytics:consolidate-search-logs` | Consolidate raw search logs into aggregated stats |

## Admin Modules

- **Search Log**: Content → Search Log — live view of raw search queries (transient, auto-purged)
- **Search Statistics**: Content → Search Statistics — aggregated stats with CSV export and reset

## License

MIT
```

### Task 12.3: Write Implementation Report

Write the final report to:

```
_ai/backlog/reports/260721_1934__IMPLEMENTATION_REPORT__extract_search_analytics_plugin.md
```

Report must include:
1. Summary
2. Files Changed (created, modified, deleted)
3. Key Changes
4. Deviations from Plan
5. Technical Decisions
6. Testing Notes
7. Usage Examples
8. Documentation Updates
9. Next Steps

---

## Appendix A: File Inventory

### New Plugin (`topdata-search-analytics-sw6`)

| # | File | Type |
|---|------|------|
| 1 | `composer.json` | MODIFY |
| 2 | `src/TopdataSearchAnalyticsSW6.php` | MODIFY |
| 3 | `src/Entity/SearchLog/SearchLogEntityDefinition.php` | NEW |
| 4 | `src/Entity/SearchLog/SearchLogEntity.php` | NEW |
| 5 | `src/Entity/SearchLog/SearchLogCollection.php` | NEW |
| 6 | `src/Entity/SearchStats/SearchStatsEntityDefinition.php` | NEW |
| 7 | `src/Entity/SearchStats/SearchStatsEntity.php` | NEW |
| 8 | `src/Entity/SearchStats/SearchStatsCollection.php` | NEW |
| 9 | `src/Migration/Migration1753122000CreateSearchLogAndStatsTable.php` | NEW |
| 10 | `src/Service/SearchAnalyticsService.php` | NEW |
| 11 | `src/Service/SearchAnalyticsApiClientInterface.php` | NEW |
| 12 | `src/Subscriber/ProductSearchSubscriber.php` | NEW |
| 13 | `src/Controller/SearchStatsController.php` | NEW |
| 14 | `src/Command/Command_ConsolidateSearchLogs.php` | NEW |
| 15 | `src/Framework/ScheduledTask/ConsolidateSearchLogsTask.php` | NEW |
| 16 | `src/Framework/ScheduledTask/ConsolidateSearchLogsTaskHandler.php` | NEW |
| 17 | `src/Resources/config/services.xml` | MODIFY |
| 18 | `src/Resources/config/config.xml` | MODIFY |
| 19 | `src/Resources/app/administration/src/main.ts` | NEW |
| 20 | `src/Resources/app/administration/src/snippet/en-GB.json` | NEW |
| 21 | `src/Resources/app/administration/src/snippet/de-DE.json` | NEW |
| 22 | `src/Resources/app/administration/src/module/topdata-sa-search-stats/index.ts` | NEW |
| 23 | `src/Resources/app/administration/src/module/topdata-sa-search-stats/page/search-stats-list/index.ts` | NEW |
| 24 | `src/Resources/app/administration/src/module/topdata-sa-search-stats/page/search-stats-list/search-stats-list.html.twig` | NEW |
| 25 | `src/Resources/app/administration/src/module/topdata-sa-search-log/index.ts` | NEW |
| 26 | `src/Resources/app/administration/src/module/topdata-sa-search-log/page/search-log-list/index.ts` | NEW |
| 27 | `src/Resources/app/administration/src/module/topdata-sa-search-log/page/search-log-list/search-log-list.html.twig` | NEW |
| 28 | `README.md` | MODIFY |
| 29 | `.gitignore` | no change needed |
| 30 | `src/Controller/StorefrontExampleController.php` | DELETE |
| 31 | `src/Controller/AdminApiExampleController.php` | DELETE |
| 32 | `src/Command/ExampleCommand.php` | DELETE |
| 33 | `src/Resources/views/storefront/example.html.twig` | DELETE |

### Old Plugin (`topdata-elasticsearch-hacks-sw6`) — Cleanup Phase

| # | File | Action |
|---|------|--------|
| 1 | `src/Subscriber/ProductSearchSubscriber.php` | DELETE |
| 2 | `src/Service/SearchAnalyticsService.php` | DELETE |
| 3 | `src/Entity/SearchLog/` (3 files) | DELETE |
| 4 | `src/Entity/SearchStats/` (3 files) | DELETE |
| 5 | `src/Controller/ZeroSearchController.php` | DELETE |
| 6 | `src/Controller/SearchStatsController.php` | DELETE |
| 7 | `src/Command/Command_ConsolidateSearchLogs.php` | DELETE |
| 8 | `src/Framework/ScheduledTask/ConsolidateSearchLogsTask.php` | DELETE |
| 9 | `src/Framework/ScheduledTask/ConsolidateSearchLogsTaskHandler.php` | DELETE |
| 10 | `src/Resources/config/services.xml` | MODIFY (remove extracted services) |
| 11 | `src/Resources/app/administration/src/main.ts` | MODIFY (remove module imports) |
| 12 | `src/Resources/app/administration/src/module/topdata-es-search-stats/` | DELETE |
| 13 | `src/Resources/app/administration/src/module/topdata-es-search-log/` | DELETE |
| 14 | `src/Resources/app/administration/src/module/topdata-es-zero-search/` | DELETE |
| 15 | `src/TopdataElasticsearchHacksSW6.php` | MODIFY (remove tdeh_search_log, tdeh_search_stats from uninstall) |
| 16 | `src/Resources/app/administration/src/snippet/en-GB.json` | MODIFY (remove zero-search, search-stats, search-log keys) |
| 17 | `src/Resources/app/administration/src/snippet/de-DE.json` | MODIFY (same) |

---

## Appendix B: What Is NOT Extracted

| Feature | Why |
|---------|-----|
| `tdeh_zero_search` table + entity + admin module | Legacy, will be dropped from old plugin in follow-up |
| Synonym management | Tightly coupled to Elasticsearch index config |
| ES query boosting / delimiter analyzer | Core ES functionality, stays in old plugin |
| Category search / suggest / exclusion | Storefront feature, unrelated to logging |
| `ProductSearchResultEvent` subscriber in old plugin | Will be deleted (new plugin has its own) |

---

## Appendix C: Naming Conventions Summary

| Context | Old Value | New Value |
|---------|-----------|-----------|
| Plugin namespace | `Topdata\TopdataElasticsearchHacksSW6` | `Topdata\TopdataSearchAnalyticsSW6` |
| Table prefix | `tdeh_` | `tdsa_` |
| Admin module prefix | `topdata-es-` | `topdata-sa-` |
| Admin route prefix | `topdata.es.` | `topdata.sa.` |
| Snippet root | `TopdataElasticsearchHacksSW6` | `TopdataSearchAnalyticsSW6` |
| CLI prefix | `topdata:es-hacks:` | `topdata:search-analytics:` |
| API route prefix | `topdata-elasticsearch-hacks-sw6` | `topdata-search-analytics-sw6` |
| Scheduled task name | `topdata_es_hacks.consolidate_search_logs` | `topdata_search_analytics.consolidate_search_logs` |
| Nav parent label | `Topdata ES` | `Search Analytics` |
