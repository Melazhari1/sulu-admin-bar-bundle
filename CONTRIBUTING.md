# Contributing

Thanks for considering a contribution!

## Setup

```bash
git clone https://github.com/elazhari/sulu-admin-bar-bundle.git
cd sulu-admin-bar-bundle
composer install
```

## Tests

```bash
vendor/bin/phpunit
```

## Coding standards

The project follows the Symfony coding standard:

```bash
vendor/bin/php-cs-fixer fix          # fix
vendor/bin/php-cs-fixer fix --dry-run --diff  # check only
```

Static analysis:

```bash
vendor/bin/phpstan analyse
```

## Pull requests

- Target the `main` branch.
- Keep the PHP 7.2 / Symfony 4.4 / Sulu 2 baseline: no syntax or API
  newer than that in `src/` (guarded `method_exists` calls are fine).
- Add a test for every behaviour change.
- Update `CHANGELOG.md` under an "Unreleased" heading.
