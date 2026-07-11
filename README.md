# AdminBarBundle

[![CI](https://github.com/elazhari/sulu-admin-bar-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/elazhari/sulu-admin-bar-bundle/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/elazhari/sulu-admin-bar-bundle.svg)](https://packagist.org/packages/elazhari/sulu-admin-bar-bundle)
[![License](https://img.shields.io/packagist/l/elazhari/sulu-admin-bar-bundle.svg)](LICENSE)

A frontend admin bar for **Sulu CMS**. Backend users who are logged into the
Sulu admin see a slim toolbar on top of the website with direct links to edit
the content they are looking at. Anonymous visitors see nothing — and the
page HTML stays fully HTTP cacheable.

```
┌──────────────────────────────────────────────────────────────────┐
│ [S] Sulu                        John Doe | Edit | Add new | Logout │
└──────────────────────────────────────────────────────────────────┘
```

## Features

- **Edit** the current page — or any custom entity (Formation, Article, …)
  detected automatically, without per-entity code.
- **Add new** content of the same type as the current page.
- **Permission-aware**: links are resolved server-side from Sulu's view
  registry, so users only ever see links they are allowed to use.
- **Cache-safe**: no user-specific markup in the page HTML.
- **Silent for visitors**: anonymous visitors trigger no extra request at
  all (session marker cookie).
- **Sulu 2 and Sulu 3** compatible, PHP >= 7.2.
- **One-command installer.**

## Installation

### Quick install (recommended)

Install the package and run the installer with the PHP binary your project
uses (the installer needs PHP >= 7.3):

```bash
composer require elazhari/sulu-admin-bar-bundle
php vendor/elazhari/sulu-admin-bar-bundle/install.php
```

Alternatively, copy the bundle into your Sulu project (e.g. to
`bundles/AdminBarBundle`) and run:

```bash
php bundles/AdminBarBundle/install.php
```

It performs every manual step listed below — composer autoload entry, bundle
registration, route import, default configuration, the security
`access_control` rule, `{{ sulu_admin_bar() }}` in `templates/base.html.twig`,
`composer dump-autoload`, `assets:install` and cache clearing.

The installer is idempotent (running it twice is safe), reports a `[WARN]`
with manual instructions for anything it cannot patch automatically (e.g. a
heavily customized security config), and accepts `--skip-commands` if you
only want the file changes without running composer/console commands.

### Manual installation

**1. Register the code** — as a composer package:

```bash
composer require elazhari/sulu-admin-bar-bundle
```

Or, as a local bundle copied to `bundles/AdminBarBundle`, add the namespace
to your project's `composer.json` and dump the autoloader:

```json
"autoload": {
    "psr-4": {
        "App\\": "src/",
        "Elazhari\\SuluAdminBarBundle\\": "bundles/AdminBarBundle/src/"
    }
}
```

```bash
composer dump-autoload
```

**2. Enable the bundle:**

```php
// config/bundles.php
return [
    // ...
    Elazhari\SuluAdminBarBundle\AdminBarBundle::class => ['all' => true],
];
```

**3. Import the route:**

```yaml
# config/routes/admin_bar.yaml
admin_bar:
    resource: '@AdminBarBundle/config/routes.yaml'
```

**4. Allow anonymous access to the endpoint.** It must stay inside the admin
firewall but must not trigger the admin login — it answers `401` itself.
Add this rule **above** the admin catch-all, using your project's admin URL
prefix (`/admin` in the Sulu skeleton):

```yaml
# config/packages/security.yaml
# (or security_admin.yaml in projects with kernel specific security configs)
access_control:
    # ...
    - { path: ^/admin/admin-bar$, roles: PUBLIC_ACCESS }
    - { path: ^/admin, roles: ROLE_USER }
```

On Symfony versions without the `PUBLIC_ACCESS` attribute use
`IS_AUTHENTICATED_ANONYMOUSLY` instead (the installer picks whichever the
file already uses).

**5. Install the assets:**

```bash
php bin/console assets:install public
```

**6. Add the bar to your base layout**, right before `</body>`:

```twig
{# templates/base.html.twig #}
        {{ sulu_admin_bar() }}
    </body>
</html>
```

Finally clear the caches:

```bash
php bin/console cache:clear
php bin/websiteconsole cache:clear
```

Log into the admin once, then open the website: the bar appears.

## How it works

In a standard Sulu setup only the admin (`^/admin`) is behind a firewall
and website responses are cached by the HTTP cache. Rendering a user-specific
bar directly into the page HTML would therefore either never see the admin
session or leak the bar into cached responses. This bundle avoids both:

1. `{{ sulu_admin_bar() }}` renders a tiny, **visitor-independent** loader
   `<script>` carrying the current page context (webspace, locale, page
   uuid or entity id) as data attributes — safe to cache.
2. While using the admin, a response listener sets a JS-readable session
   marker cookie (`sulu_admin_bar`) and removes it again on logout. The
   cookie carries no data; it only tells the loader that an admin session
   exists, so **anonymous visitors never call the endpoint at all**.
3. When the marker is present, the script calls `GET <admin-prefix>/admin-bar`
   (`/admin/admin-bar` by default), which runs through the **admin firewall**,
   so the Sulu admin session is available there. The prefix is detected from
   the project's `admin` firewall pattern automatically — see
   `admin_route_prefix` below.
4. If the user is authenticated, the endpoint returns their name and the
   permission-checked admin URLs; the script injects the stylesheet and the
   bar. Otherwise it returns `401`, the stale marker is dropped and nothing
   is rendered.

## What the bar shows

| Element | Behaviour |
| --- | --- |
| Sulu logo | Links to the Sulu admin. |
| User name | Full name of the logged-in Sulu user (falls back to the username). |
| Edit | Opens the current page or entity in its Sulu admin edit form. |
| Add new | Opens the creation form for the current content type; outside of any content context it opens the page list of the webspace. |
| Logout | Calls the Sulu admin logout route. |

Permissions are checked server-side with Sulu's `SecurityChecker` — against
the `sulu.webspaces.<webspace>` security context for pages and against the
entity's admin views (plus the optional configured `security_context`) for
custom entities — so links the user is not allowed to use are never rendered.

## Configuration

Everything works without configuration. The full reference:

```yaml
# config/packages/admin_bar.yaml
admin_bar:
    enabled: true   # set to false to remove the loader snippet entirely

    # URL prefix the Sulu admin lives under. The admin bar endpoint is
    # registered below it so the request runs through the admin firewall.
    # Detected automatically from the "admin" firewall pattern of your
    # security configuration ("/admin" in the Sulu skeleton, "/_private"
    # in older setups, ...); only set it when the detection cannot work,
    # e.g. with a renamed admin firewall.
    #admin_route_prefix: /admin

    # Texts of the toolbar links — override them to localize the bar.
    labels:
        edit: Edit
        add: Add new
        logout: Logout

    # Optional per-entity extras — see "Custom entities" below.
    entities:
        formation:                                        # request attribute
            resource_key: formations                      # Sulu resource key
            security_context: sulu.formations.formation   # optional extra gate
            routes: [formation_detail]                    # optional route names
```

## Custom entities

Sulu projects often route Doctrine entities through the RouteBundle with a
`RouteDefaultsProviderInterface` implementation. When such a provider exposes
the entity as a request attribute in its route defaults:

```php
public function getByEntity($entityClass, $id, $locale, $object = null)
{
    return [
        '_controller' => '...',
        'id' => $formation->getId(),
        'formation' => $formation,   // <- request attribute
    ];
}
```

the admin bar links to the entity's admin form **automatically**. Detection
is convention based: any request attribute holding an object with a
`RESOURCE_KEY` class constant (the standard Sulu entity convention) and a
scalar `getId()` is linkable.

Entity detail pages that are **not** served through the Sulu route system
but by plain Symfony routes (e.g. `/formation/{slug}/{id}`) carry no entity
object in the request attributes — those are detected automatically too.
The loader forwards the route name and URL path together with the numeric
`id` route parameter, and the authenticated endpoint matches them against
the Doctrine entities following the `RESOURCE_KEY` convention (resource key
and class short name, tolerant of singular/plural and `_`/`-`/`.`
separators: a route `formation` or a path segment `formation-domains`
finds the `formations` / `formation_domains` entities).

The admin URLs are just as dynamic: the endpoint looks the edit and add form
views up in Sulu's **view registry** (`sulu_admin.view_registry`) by resource
key and uses the actual registered view paths. Because Admin classes register
views per user, a user without access to the entity simply gets no link.

Configuration under `admin_bar.entities` is only needed for two cases:

- `routes` — route names whose name/path the automatic detection cannot
  relate to the entity (e.g. a route named `training_sheet` rendering a
  `Formation`). Explicitly listed route names always win over the
  automatic matching and are resolved without the name heuristic.
- `security_context` — an additional permission gate. Without it a link is
  shown whenever the current user has a matching admin view (which usually
  means the `view` permission); with it the `edit`/`add` permission of that
  context is required on top.

Notes:

- Explicitly configured attributes win over auto-detection, in the
  configured order. With auto-detection alone, the request attribute order
  decides (the route defaults order), which normally puts the primary
  entity first.
- The entity must have a scalar, non-empty `getId()`; the endpoint only
  accepts numeric ids.

## Security notes

- The bar never renders for anonymous visitors; the decision is made by the
  authenticated `<admin-prefix>/admin-bar` endpoint, not by cacheable page
  HTML.
- The `sulu_admin_bar` marker cookie is a pure presence flag (value `1`,
  session lifetime, `SameSite=Lax`). It is never trusted server-side: it
  only prevents pointless endpoint calls from visitors without an admin
  session. Deleting or forging it changes nothing security-wise.
- The endpoint validates the `webspace`, `locale`, `uuid` and `id` query
  parameters against known webspaces/localizations and a strict UUID
  pattern; entity ids must be numeric and the `resourceKey` must match a
  view registered for the current user in the Sulu view registry.
- The `route` and `path` parameters of the automatic entity detection are
  only ever compared against the Doctrine entities' resource keys; a
  derived resource key goes through the exact same permission-filtered
  view registry lookup as an explicit one.
- The JSON response is sent with `Cache-Control: private, no-store`.
- The toolbar DOM is built with `textContent` (no HTML injection via user
  names or page data).

## Compatibility

- PHP >= 7.2 (the optional installer script needs PHP >= 7.3)
- Sulu ^2.0 || ^3.0
- Symfony ^4.4 || ^5.4 || ^6.4 || ^7.0
- Browsers: the loader uses `fetch` and `URLSearchParams` — every evergreen
  browser works; Internet Explorer does not (visitors are unaffected either
  way, the script only acts for logged-in admin users).

The bundle intentionally uses the classic `Bundle` + DI extension pattern and
PHP 7.2 compatible syntax so the same code runs on legacy Sulu 2 projects and
current Sulu 3 projects. The current page is detected via the `object`
request attribute on Sulu 3 and via the `structure` attribute (`PageBridge`)
on Sulu 2; admin URL patterns and the `sulu.webspaces.<webspace>` security
context are identical in both major versions.

## Bundle structure

```
AdminBarBundle/
├── composer.json
├── install.php                  # one-shot project installer
├── LICENSE
├── README.md
├── config/
│   ├── routes.yaml              # <admin-prefix>/admin-bar JSON endpoint
│   └── services.yaml            # service definitions
├── public/
│   ├── admin-bar.css            # toolbar styles (loaded only when logged in)
│   └── admin-bar.js             # loader + toolbar rendering
├── src/
│   ├── AdminBarBundle.php       # bundle class
│   ├── DependencyInjection/
│   │   ├── AdminBarExtension.php  # loads services, "admin_bar" config
│   │   └── Configuration.php
│   ├── Controller/
│   │   └── AdminBarController.php # authenticated JSON endpoint
│   ├── EventListener/
│   │   └── AdminSessionCookieListener.php # session marker cookie
│   ├── Resolver/
│   │   └── EntityResourceKeyResolver.php # route/path → resource key
│   └── Twig/
│       └── AdminBarExtension.php  # {{ sulu_admin_bar() }}
├── templates/
│   └── admin_bar.html.twig      # cache-safe loader snippet
└── tests/                       # PHPUnit test suite (vendor/bin/phpunit)
```

## License

Released under the [MIT license](LICENSE).
