# Changelog

All notable changes to this project will be documented in this file.

## [1.0.2] - 2026-07-24

### Fixed
- Fixed search term log duplication where consecutive identical searches (such as suggest and final result hits) were tracked as separate rows.
- Excluded AJAX search listing updates (filtering, sorting, and pagination) from creating incorrect zero-result entries.

### Changed
- Swallowing of database exceptions in the search subscriber now logs a warning via `LoggerInterface` instead of silently discarding them.
