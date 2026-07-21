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
