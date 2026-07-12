# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2026-07-12

### Changed

- **Package renamed** from `elazhari/sulu-admin-bar-bundle` to
  `melazhari/sulu-admin-bar-bundle`. The PHP namespace
  (`Elazhari\SuluAdminBarBundle`) is unchanged, so no code changes are
  needed — only the `composer require` name.
- The toolbar now shows the official Sulu logo mark (linking to the admin)
  instead of the placeholder "S" square with a "Sulu" text label.

## [1.2.1] - 2026-07-11

### Fixed

- **Articles are now supported.** On Sulu 3, article pages (rendered with an
  `ArticleDimensionContent` as `object` request attribute) previously fell
  through to the generic webspace context: the bar showed no edit link and
  the add link pointed to the page list. The Twig extension now exposes the
  article UUID as entity id and the endpoint accepts UUID entity ids (it
  only accepted numeric Doctrine ids before), so the edit/add links resolve
  to the article form views from the admin view registry. On Sulu 2,
  articles rendered by SuluArticleBundle (`ArticleDocument` as `object`
  request attribute) are detected as well.

## [1.2.0] - 2026-07-11

### Changed

- The admin URL prefix of the endpoint route is no longer hardcoded to
  `/_private`: it is now **detected automatically** from the `admin`
  firewall pattern of the project's security configuration (`/admin` in
  the Sulu skeleton) and can be overridden with the new
  `admin_bar.admin_route_prefix` option. The endpoint route path is built
  from the `%admin_bar.admin_route_prefix%` container parameter.
- The installer detects the project's admin prefix from the security
  `access_control` catch-all instead of assuming `^/_private`, so it now
  works out of the box on stock Sulu skeletons (`/admin`) as well as on
  projects with a custom admin prefix.

### Upgrade notes

- Projects whose `admin` firewall pattern is `^/_private...` keep working
  unchanged (the detection resolves to the same path). Only projects with
  a renamed admin firewall need to set `admin_bar.admin_route_prefix`
  explicitly.
- Make sure your security `access_control` rule matches the endpoint path,
  e.g. `- { path: ^/admin/admin-bar$, roles: PUBLIC_ACCESS }` above the
  `^/admin` catch-all.

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
