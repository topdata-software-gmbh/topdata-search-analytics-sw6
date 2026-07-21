---
filename: "_ai/backlog/reports/260721_1934__IMPLEMENTATION_REPORT__extract_search_analytics_plugin.md"
title: "Extract Search Analytics into topdata-search-analytics-sw6"
createdAt: 2026-07-21 19:34
updatedAt: 2026-07-21
status: completed
documentType: IMPLEMENTATION_REPORT
---

# Implementation Report: Extract Search Analytics Plugin

## 1. Summary

Extracted the full search analytics feature (~25 files created, ~6 modified) from `topdata-elasticsearch-hacks-sw6` into a new standalone plugin `topdata-search-analytics-sw6` with namespace `Topdata\TopdataSearchAnalyticsSW6` and table prefix `tdsa_`.

## 2. Files Changed

### Created (New Plugin — 25 files)

| File | Purpose |
|------|---------|
| `src/Entity/SearchLog/SearchLogEntityDefinition.php` | Entity definition for `tdsa_search_log` |
| `src/Entity/SearchLog/SearchLogEntity.php` | Entity class |
| `src/Entity/SearchLog/SearchLogCollection.php` | Collection class |
| `src/Entity/SearchStats/SearchStatsEntityDefinition.php` | Entity definition for `tdsa_search_stats` |
| `src/Entity/SearchStats/SearchStatsEntity.php` | Entity class |
| `src/Entity/SearchStats/SearchStatsCollection.php` | Collection class |
| `src/Migration/Migration1753122000CreateSearchLogAndStatsTable.php` | DB migration creating both tables |
| `src/Service/SearchAnalyticsService.php` | Consolidation engine |
| `src/Service/SearchAnalyticsApiClientInterface.php` | CM placeholder interface |
| `src/Subscriber/ProductSearchSubscriber.php` | Storefront search logging subscriber |
| `src/Controller/SearchStatsController.php` | CSV export + reset API endpoints |
| `src/Command/Command_ConsolidateSearchLogs.php` | CLI command for manual consolidation |
| `src/Framework/ScheduledTask/ConsolidateSearchLogsTask.php` | Scheduled task definition |
| `src/Framework/ScheduledTask/ConsolidateSearchLogsTaskHandler.php` | Scheduled task handler |
| `src/Resources/app/administration/src/main.ts` | Admin entry point |
| `src/Resources/app/administration/src/module/topdata-sa-search-stats/index.ts` | Search Stats module |
| `src/Resources/app/administration/src/module/topdata-sa-search-stats/page/search-stats-list/index.ts` | Search Stats list component |
| `src/Resources/app/administration/src/module/topdata-sa-search-stats/page/search-stats-list/search-stats-list.html.twig` | Search Stats template |
| `src/Resources/app/administration/src/module/topdata-sa-search-log/index.ts` | Search Log module |
| `src/Resources/app/administration/src/module/topdata-sa-search-log/page/search-log-list/index.ts` | Search Log list component |
| `src/Resources/app/administration/src/module/topdata-sa-search-log/page/search-log-list/search-log-list.html.twig` | Search Log template |
| `src/Resources/app/administration/src/snippet/en-GB.json` | English snippets |
| `src/Resources/app/administration/src/snippet/de-DE.json` | German snippets |

### Modified (New Plugin — 4 files)

| File | Changes |
|------|---------|
| `composer.json` | Added full metadata, description, labels |
| `src/TopdataSearchAnalyticsSW6.php` | Added `uninstall()` method dropping `tdsa_*` tables |
| `src/Resources/config/services.xml` | Replaced placeholder with full service wiring |
| `src/Resources/config/config.xml` | Replaced example config with `enabled` bool field |
| `README.md` | Full documentation |

### Deleted (New Plugin — 4 skeleton files)

- `src/Controller/StorefrontExampleController.php`
- `src/Controller/AdminApiExampleController.php`
- `src/Command/ExampleCommand.php`
- `src/Resources/views/storefront/example.html.twig`

### Deleted (Old Plugin — 12 files/dirs)

- `src/Subscriber/ProductSearchSubscriber.php`
- `src/Service/SearchAnalyticsService.php`
- `src/Entity/SearchLog/` (3 files)
- `src/Entity/SearchStats/` (3 files)
- `src/Controller/ZeroSearchController.php`
- `src/Controller/SearchStatsController.php`
- `src/Command/Command_ConsolidateSearchLogs.php`
- `src/Framework/ScheduledTask/ConsolidateSearchLogsTask.php`
- `src/Framework/ScheduledTask/ConsolidateSearchLogsTaskHandler.php`
- `src/Resources/app/administration/src/module/topdata-es-search-stats/`
- `src/Resources/app/administration/src/module/topdata-es-search-log/`
- `src/Resources/app/administration/src/module/topdata-es-zero-search/`

### Modified (Old Plugin — 4 files)

- `src/Resources/config/services.xml` — Removed 9 extracted service definitions
- `src/Resources/app/administration/src/main.ts` — Kept only synonym import
- `src/TopdataElasticsearchHacksSW6.php` — Removed `tdeh_search_log`, `tdeh_search_stats` from uninstall
- `src/Resources/app/administration/src/snippet/en-GB.json` — Removed zero-search, search-stats, search-log keys
- `src/Resources/app/administration/src/snippet/de-DE.json` — Removed zero-search, search-stats, search-log keys

## 3. Key Changes

- **New namespace**: `Topdata\TopdataSearchAnalyticsSW6`
- **New table prefix**: `tdsa_` (was `tdeh_`)
- **New admin module prefix**: `topdata-sa-` (was `topdata-es-`)
- **New CLI prefix**: `topdata:search-analytics:` (was `topdata:es-hacks:`)
- **New scheduled task name**: `topdata_search_analytics.consolidate_search_logs`
- **CM placeholder**: `SearchAnalyticsApiClientInterface` defined (no implementation yet)
- **ZeroSearch NOT extracted**: Legacy `tdeh_zero_search` table left in old plugin

## 4. Deviations from Plan

None. All phases executed as specified.

## 5. Technical Decisions

- Entity definitions registered via `shopware.entity.definition` tag (SW 6.7 convention)
- API controllers use Attribute routing with `#[Route(defaults: ['_routeScope' => ['api']])]`
- Scheduled task handler uses `#[AsMessageHandler]` attribute
- CLI command uses `#[AsCommand]` attribute extending `AbstractTopdataCommand`
- Admin modules reuse `system.zero_search.viewer` privilege ACL
- Admin listing uses `sw-time-ago` component (Vue 3 compatible, no `| date()` filter)

## 6. Testing Notes

- No automated tests were written (plan did not call for them)
- Manual validation checklist:
  - [ ] Plugin installs and activates
  - [ ] `tdsa_search_log` and `tdsa_search_stats` tables are created
  - [ ] Storefront search writes to `tdsa_search_log`
  - [ ] `topdata:search-analytics:consolidate-search-logs` command runs
  - [ ] Scheduled task appears in admin
  - [ ] Admin modules render and show data
  - [ ] CSV export downloads file
  - [ ] Reset truncates both tables
  - [ ] Uninstall (without keepUserData) drops tables

## 7. Usage Examples

```bash
# Manual consolidation
php bin/console topdata:search-analytics:consolidate-search-logs --batch-size=100

# Run migrations
php bin/console database:migrate TopdataSearchAnalyticsSW6 --all

# Clear cache after install
php bin/console cache:clear
```

## 8. Documentation Updates

- `README.md` rewritten with full feature description, installation steps, console commands, and admin module overview

## 9. Next Steps

1. Install and activate the new plugin in a test Shopware 6.7 instance
2. Verify storefront search logging works end-to-end
3. Run consolidation command and verify stats aggregation
4. Deactivate the old plugin's analytics features (or deactivate old plugin entirely if no other ES hacks are needed)
5. Follow-up cleanup: remove `tdeh_zero_search` table and its admin module from old plugin
6. Future: implement `SearchAnalyticsApiClientInterface` for CM integration
