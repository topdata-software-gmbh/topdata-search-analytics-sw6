---
filename: "_ai/backlog/reports/260724_2237__IMPLEMENTATION_REPORT__deduplicate_and_filter_search_logs.md"
title: "Report: Fix search logging duplication and incorrect result counts"
createdAt: 2026-07-24 22:37
updatedAt: 2026-07-24 22:37
planFile: "_ai/backlog/active/260724_2237__IMPLEMENTATION_PLAN__deduplicate_and_filter_search_logs.md"
project: "SW6.7 Plugin"
status: completed
filesCreated: 2
filesModified: 2
filesDeleted: 0
tags: [search, subscriber, logging, shopware6]
documentType: IMPLEMENTATION_REPORT
---

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
