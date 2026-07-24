---
filename: "_ai/backlog/active/260724_2237__IMPLEMENTATION_PLAN__deduplicate_and_filter_search_logs.md"
title: "Fix search logging duplication and incorrect result counts"
createdAt: 2026-07-24 22:37
updatedAt: 2026-07-24 22:42
status: completed
completedAt: 2026-07-24 23:00
priority: medium
tags: [search, subscriber, logging, shopware6]
estimatedComplexity: simple
documentType: IMPLEMENTATION_PLAN
---

## 1. Problem Description
The search analytics plugin currently suffers from two distinct tracking imperfections:
1. **Duplicate Consecutive Log Entries:** When a customer types a term in the live search bar and then executes a full search, the plugin logs the search twice in quick succession (once for `ProductEvents::PRODUCT_SUGGEST_RESULT` and once for `ProductEvents::PRODUCT_SEARCH_RESULT`). Both entries are identical but logged as separate database rows.
2. **Incorrect Zero-Result Log Entries:** When a user is on the search results page and applies filters, changes sorting, or paginates, Shopware sends an AJAX request to update the product listing. Because the request URL still includes `?search=term`, the search subscriber is triggered again. If filters reduce the matches to 0, a new entry is logged with 0 results, even though the user didn't perform a new search.

---

## 2. Executive Summary of the Solution
The proposed solution implements a two-layered defense in the `ProductSearchSubscriber`:
1. **Filter Out Listing AJAX Updates:** Detect whether the request is an AJAX listing update (such as sorting, filtering, or pagination) on the search result page. We do this by checking if the request route is `frontend.search.page` and is an AJAX request (`$request->isXmlHttpRequest()`). If both are true, we skip logging.
2. **Deduplicate Real-time Queries:** Look up the database to see if the last query in the current session matches the exact same search term and was logged within a short time threshold (e.g., 15 seconds). If so, we update the existing record with the latest timestamp and result count instead of creating a duplicate row.

---

## 3. Project Environment Details
- Project Name: SW6.7 Plugin
- Backend root: `src`
- PHP Version: 8.2 / 8.3 / 8.4

---

## 4. Implementation Phases

### Phase 1: Code Modifications

We will update the `ProductSearchSubscriber` to ignore listing AJAX updates and deduplicate immediate consecutive queries.

#### [MODIFY] `src/Subscriber/ProductSearchSubscriber.php`
```php
<?php declare(strict_types=1);

namespace Topdata\TopdataSearchAnalyticsSW6\Subscriber;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestResultEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSearchSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    )
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
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Skip AJAX filter / pagination / sorting requests on the main search page
        if ($route === 'frontend.search.page' && $request->isXmlHttpRequest()) {
            return;
        }

        $term = $request->get('search');

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

        $resultCount = $event->getResult()->getTotal();
        $sessionToken = $event->getSalesChannelContext()->getToken();

        try {
            // Deduplicate: check the last log for this session token
            $lastLog = $this->connection->fetchAssociative(
                'SELECT `id`, `term`, `result_count`, `created_at`
                 FROM `tdsa_search_log`
                 WHERE `session_token` = :session_token
                 ORDER BY `created_at` DESC
                 LIMIT 1',
                ['session_token' => $sessionToken]
            );

            if ($lastLog) {
                $lastCreatedAt = new \DateTime($lastLog['created_at']);
                $now = new \DateTime();
                $timeDiff = $now->getTimestamp() - $lastCreatedAt->getTimestamp();

                // If the term is identical and was logged within the last 15 seconds
                if ($lastLog['term'] === $term && $timeDiff <= 15) {
                    $this->connection->executeStatement(
                        'UPDATE `tdsa_search_log`
                         SET `result_count` = :result_count, `created_at` = :now
                         WHERE `id` = :id',
                        [
                            'result_count' => $resultCount,
                            'now' => $now->format('Y-m-d H:i:s.v'),
                            'id' => $lastLog['id'],
                        ]
                    );
                    return;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Search log dedup lookup failed, falling back to insert', [
                'error' => $e->getMessage(),
                'term' => $term,
                'session_token' => $sessionToken,
            ]);
        }

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
            $this->logger->warning('Search log insert failed', [
                'error' => $e->getMessage(),
                'term' => $term,
                'session_token' => $sessionToken,
            ]);
        }
    }
}
```

---

#### [MODIFY] `src/Resources/config/services.xml`

Add a `logger` argument to the `ProductSearchSubscriber` service definition:

```xml
<service id="Topdata\TopdataSearchAnalyticsSW6\Subscriber\ProductSearchSubscriber">
    <argument type="service" id="Doctrine\DBAL\Connection" key="$connection"/>
    <argument type="service" id="Psr\Log\LoggerInterface" key="$logger"/>
    <tag name="kernel.event_subscriber"/>
</service>
```

> The `Psr\Log\LoggerInterface` service is auto-registered by the Symfony framework container. No additional wiring is needed beyond this argument.

---

### Phase 2: Housekeeping & Documentation

We will document the changes in a `CHANGELOG.md` file.

#### [NEW FILE] `CHANGELOG.md`
```markdown
# Changelog

All notable changes to this project will be documented in this file.

## [1.0.2] - 2026-07-24

### Fixed
- Fixed search term log duplication where consecutive identical searches (such as suggest and final result hits) were tracked as separate rows.
- Excluded AJAX search listing updates (filtering, sorting, and pagination) from creating incorrect zero-result entries.

### Changed
- Swallowing of database exceptions in the search subscriber now logs a warning via `LoggerInterface` instead of silently discarding them.
```

---

### Phase 3: Project Report Generation

We will draft and finalize the implementation report.

#### [NEW FILE] `_ai/backlog/reports/260724_2237__IMPLEMENTATION_REPORT__deduplicate_and_filter_search_logs.md`
```yaml
---
filename: "_ai/backlog/reports/260724_2237__IMPLEMENTATION_REPORT__deduplicate_and_filter_search_logs.md"
title: "Report: Fix search logging duplication and incorrect result counts"
createdAt: 2026-07-24 22:37
updatedAt: 2026-07-24 22:37
planFile: "_ai/backlog/active/260724_2237__IMPLEMENTATION_PLAN__deduplicate_and_filter_search_logs.md"
project: "SW6.7 Plugin"
status: completed
completedAt: 2026-07-24 23:00
filesCreated: 2
filesModified: 2
filesDeleted: 0
tags: [search, subscriber, logging, shopware6]
documentType: IMPLEMENTATION_REPORT
---
```

# Implementation Report

## 1. Summary
The duplicate logging and filter-induced zero-result logs have been corrected. The plugin now skips recording on AJAX-based filter requests on the search page and updates existing log entries when identical terms are queried in quick succession during the same session.

## 2. Files Changed
### New Files
- `CHANGELOG.md`: Documents user-facing changes and version notes.
- `_ai/backlog/reports/260724_2237__IMPLEMENTATION_REPORT__deduplicate_and_filter_search_logs.md`: This report detailing the implementation.

### Modified Files
- `src/Subscriber/ProductSearchSubscriber.php`: Updated search event logic to apply early return criteria, query deduplication, and exception logging via `LoggerInterface`.
- `src/Resources/config/services.xml`: Added `Psr\Log\LoggerInterface` argument to `ProductSearchSubscriber` service definition.

## 3. Key Changes
- **AJAX Filter Suppression:** Added checking for `frontend.search.page` and `$request->isXmlHttpRequest()` to return early and prevent logging of filtered, paginated, or sorted lists.
- **Identical Query Deduplication:** Added an database lookup that queries the last logged search item for the active session. If it matches the term and falls within a 15-second window, it updates the record timestamp and result count instead of creating a new row.
- **Exception Logging:** Both catch blocks now log a warning via `LoggerInterface` instead of silently swallowing the exception.

## 4. Technical Decisions
- **Updating vs. Skipping:** We decided to update the previous identical record instead of discarding the subsequent request. This ensures that the most up-to-date result count and timestamp are preserved while keeping the database records clean.
- **Index Optimization:** The database lookup utilizes the existing `idx.tdsa_search_log.session_token` index to query the single last entry, maintaining performance efficiency with a complexity of O(log N).
- **Exception Logging:** Database exceptions in the subscriber are logged via `LoggerInterface` at `warning` level instead of being silently swallowed. This preserves the graceful fallback (the subscriber continues without crashing) while making DB failures observable in the Symfony log.

## 5. Testing Notes
- **Suggest + Search test:** Enter a search term in the storefront. Confirm that only a single log entry is written to `tdsa_search_log` (or visible in the search log grid) with the correct result count.
- **Filter test:** Click on sidebar filters in the search results page. Verify that no additional search logs are generated during this activity.
