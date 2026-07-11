# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-07-11

### Added

- Automatic entity detection for detail pages served by **plain Symfony
  routes** (no entity object in the request attributes): the loader now
  forwards the route name, URL path and numeric `id` parameter, and the
  authenticated endpoint matches them against the Doctrine entities
  following the `RESOURCE_KEY` convention (`EntityResourceKeyResolver`).
  The `admin_bar.entities` `routes` option is no longer required for such
  pages; it remains available as an explicit override.

## [1.0.0] - 2026-07-02

### Added

- Frontend admin bar for Sulu CMS 2 and 3 with permission-checked
  **Edit** / **Add new** / **Logout** links.
- Automatic detection of custom entities exposed as request attributes
  (`RESOURCE_KEY` convention), with optional per-entity configuration
  (`resource_key`, `security_context`, `routes`).
- Cache-safe loader snippet (`{{ sulu_admin_bar() }}`): no user-specific
  markup in the page HTML, no endpoint call for anonymous visitors
  (session marker cookie).
- Configurable toolbar labels (`admin_bar.labels`) for localization.
- One-command project installer (`install.php`).
