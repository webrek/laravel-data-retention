# Changelog

All notable changes to `webrek/laravel-data-retention` are documented here. The
format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and the
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-06-29

### Added

- Declarative retention policies on Eloquent models via the `HasRetention`
  trait and a fluent `RetentionPolicy` (`since()`, `keepFor()`, `where()`,
  `includeTrashed()`, `name()`).
- Three actions for aged-out rows: `delete()` (soft-delete aware),
  `forceDelete()` and `anonymize()` with literal or per-row closure values and
  an optional mark column for idempotent runs.
- `DataRetention::register()` to attach a policy to models you cannot edit.
- `retention:run` (with `--model`, `--dry-run` and `--chunk`) and
  `retention:list` artisan commands. The runner pages by primary key, so a run
  resumes safely after a crash.
- A `data_retention_log` audit table recording every purge and anonymization,
  with a `RecordsRetained` event per policy run.
- Publishable config and migration; supports Laravel 12 and 13 on PHP 8.2+.

[Unreleased]: https://github.com/webrek/laravel-data-retention/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/webrek/laravel-data-retention/releases/tag/v1.0.0
