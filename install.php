#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * This file is part of the AdminBarBundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/*
 * AdminBarBundle installer.
 *
 * Wires the bundle into the surrounding Sulu project after the bundle
 * directory has been copied into it (or installed with composer):
 *
 *   1. composer.json          – PSR-4 autoload entry for "Elazhari\SuluAdminBarBundle\"
 *                               (skipped for composer installs under vendor/)
 *   2. config/bundles.php     – bundle registration
 *   3. config/routes/         – admin_bar.yaml route import
 *   4. config/packages/       – admin_bar.yaml default configuration
 *   5. security.yaml          – PUBLIC_ACCESS rule for /_private/admin-bar
 *   6. templates/base.html.twig – {{ sulu_admin_bar() }} before </body>
 *   7. composer dump-autoload, assets:install, cache:clear
 *
 * Usage:
 *   php bundles/AdminBarBundle/install.php [--skip-commands]
 *
 * The script is idempotent: running it again on an already configured
 * project only reports "SKIP" for every step.
 *
 * It intentionally runs on PHP >= 7.3 so it still works when the default
 * CLI "php" is older than the project's PHP.
 */

\error_reporting(\E_ALL);

const STATUS_OK = "\033[32m[OK]\033[0m  ";
const STATUS_SKIP = "\033[36m[SKIP]\033[0m";
const STATUS_WARN = "\033[33m[WARN]\033[0m";
const STATUS_FAIL = "\033[31m[FAIL]\033[0m";

/** @var list<string> $argv */
$skipCommands = \in_array('--skip-commands', $argv, true);

$bundleDir = __DIR__;
$projectDir = findProjectDir($bundleDir);

if (null === $projectDir) {
    output(STATUS_FAIL, 'Could not find the project root (composer.json + config/bundles.php). Copy the bundle into your Sulu project first, e.g. to "bundles/AdminBarBundle".');
    exit(1);
}

$relativeBundleDir = \str_replace('\\', '/', \substr($bundleDir, \strlen($projectDir) + 1));

echo "AdminBarBundle installer\n";
echo "Project: {$projectDir}\n";
echo "Bundle:  {$relativeBundleDir}\n\n";

$warnings = 0;

updateComposerJson($projectDir, $relativeBundleDir);
updateBundlesPhp($projectDir);
createFileIfMissing(
    $projectDir . '/config/routes/admin_bar.yaml',
    "admin_bar:\n    resource: '@AdminBarBundle/config/routes.yaml'\n",
    'route import (config/routes/admin_bar.yaml)'
);
createFileIfMissing(
    $projectDir . '/config/packages/admin_bar.yaml',
    "admin_bar:\n    enabled: true\n"
    . "\n"
    . "    # Custom entities are detected automatically: via a request\n"
    . "    # attribute with a RESOURCE_KEY constant, or by matching the\n"
    . "    # route name / URL path against the Doctrine entities. Optional\n"
    . "    # extra configuration per entity (stricter permission gate or\n"
    . "    # route names the automatic detection cannot guess):\n"
    . "    #entities:\n"
    . "    #    formation:\n"
    . "    #        resource_key: formations\n"
    . "    #        security_context: sulu.formations.formation  # optional\n"
    . "    #        routes: [formation_detail]                   # optional\n",
    'package config (config/packages/admin_bar.yaml)'
);
updateSecurityYaml($projectDir);
updateBaseTemplate($projectDir);

if ($skipCommands) {
    output(STATUS_SKIP, 'commands (--skip-commands): run "composer dump-autoload", "php bin/console assets:install public" and clear the caches manually.');
} else {
    runCommands($projectDir);
}

echo "\nDone" . ($warnings > 0 ? " with {$warnings} warning(s) – see above for manual steps." : '.') . "\n";
echo "Log into /_private and open the website to see the admin bar.\n";

exit($warnings > 0 ? 2 : 0);

function findProjectDir(string $dir): ?string
{
    for ($i = 0; $i < 4; ++$i) {
        $dir = \dirname($dir);

        if (\is_file($dir . '/composer.json') && \is_file($dir . '/config/bundles.php')) {
            return $dir;
        }
    }

    return null;
}

function output(string $status, string $message): void
{
    global $warnings;

    if (STATUS_WARN === $status) {
        ++$warnings;
    }

    echo $status . ' ' . $message . "\n";
}

function updateComposerJson(string $projectDir, string $relativeBundleDir): void
{
    // Installed with composer: the package autoloads from vendor/ already,
    // adding a project PSR-4 entry would be wrong.
    if (0 === \strncmp($relativeBundleDir, 'vendor/', 7)) {
        output(STATUS_SKIP, 'composer.json autoload entry (composer install, already autoloaded)');

        return;
    }

    $file = $projectDir . '/composer.json';
    $json = (string) \file_get_contents($file);

    if (0 === \strncmp($json, "\xEF\xBB\xBF", 3)) {
        $json = \substr($json, 3);
    }

    /** @var array<string, mixed> $composer */
    $composer = \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

    if (isset($composer['autoload']['psr-4']['Elazhari\\SuluAdminBarBundle\\'])) {
        output(STATUS_SKIP, 'composer.json autoload entry already present');

        return;
    }

    $composer['autoload']['psr-4']['Elazhari\\SuluAdminBarBundle\\'] = $relativeBundleDir . '/src/';

    \file_put_contents(
        $file,
        \json_encode($composer, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) . "\n"
    );

    output(STATUS_OK, 'composer.json: added "Elazhari\\SuluAdminBarBundle\\" PSR-4 autoload entry');
}

function updateBundlesPhp(string $projectDir): void
{
    $file = $projectDir . '/config/bundles.php';
    $content = (string) \file_get_contents($file);

    if (false !== \strpos($content, 'SuluAdminBarBundle\AdminBarBundle')) {
        output(STATUS_SKIP, 'config/bundles.php registration already present');

        return;
    }

    $position = \strrpos($content, '];');

    if (false === $position) {
        output(STATUS_WARN, "config/bundles.php: could not find \"];\" – add this line manually:\n        Elazhari\\SuluAdminBarBundle\\AdminBarBundle::class => ['all' => true],");

        return;
    }

    $content = \substr_replace(
        $content,
        "    Elazhari\\SuluAdminBarBundle\\AdminBarBundle::class => ['all' => true],\n];",
        $position,
        2
    );

    \file_put_contents($file, $content);
    output(STATUS_OK, 'config/bundles.php: registered Elazhari\SuluAdminBarBundle\AdminBarBundle');
}

function createFileIfMissing(string $file, string $content, string $description): void
{
    if (\is_file($file)) {
        output(STATUS_SKIP, $description . ' already exists');

        return;
    }

    \file_put_contents($file, $content);
    output(STATUS_OK, 'created ' . $description);
}

function updateSecurityYaml(string $projectDir): void
{
    // Sulu projects keep the admin firewall either in security.yaml or, with
    // kernel specific configs, in security_admin.yaml — patch whichever file
    // contains the "^/_private" access_control catch-all.
    $candidates = [
        $projectDir . '/config/packages/security_admin.yaml',
        $projectDir . '/config/packages/security.yaml',
    ];

    $files = \array_values(\array_filter($candidates, 'is_file'));

    if ([] === $files) {
        output(STATUS_WARN, "config/packages/security.yaml not found – add this access_control rule above the ^/_private catch-all manually:\n        - { path: ^/_private/admin-bar$, roles: PUBLIC_ACCESS }");

        return;
    }

    foreach ($files as $file) {
        $name = \basename($file);
        $content = (string) \file_get_contents($file);

        if (false !== \strpos($content, '_private/admin-bar')) {
            output(STATUS_SKIP, $name . ' access_control rule already present');

            return;
        }

        // Match the anonymous-access role already used in the file so the
        // rule works on every supported Symfony version.
        $role = false !== \strpos($content, 'IS_AUTHENTICATED_ANONYMOUSLY')
            ? 'IS_AUTHENTICATED_ANONYMOUSLY'
            : 'PUBLIC_ACCESS';
        $accessControlLine = '- { path: ^/_private/admin-bar$, roles: ' . $role . ' }';

        // Insert directly above the "^/_private" catch-all so the endpoint
        // answers 401 itself instead of triggering the admin login.
        $updated = \preg_replace(
            '/^([ \t]*)- \{ path: \^\/_private, roles: ROLE_USER \}/m',
            "\$1{$accessControlLine}\n\$1- { path: ^/_private, roles: ROLE_USER }",
            $content,
            1,
            $count
        );

        if (null !== $updated && $count > 0) {
            \file_put_contents($file, $updated);
            output(STATUS_OK, $name . ': added ' . $role . ' rule for ^/_private/admin-bar$');

            return;
        }
    }

    output(STATUS_WARN, "security config: could not find the \"^/_private\" access_control catch-all in " . \implode(' or ', \array_map('basename', $files)) . " – add this rule above it manually:\n        - { path: ^/_private/admin-bar$, roles: PUBLIC_ACCESS }");
}

function updateBaseTemplate(string $projectDir): void
{
    $file = $projectDir . '/templates/base.html.twig';

    if (!\is_file($file)) {
        output(STATUS_WARN, 'templates/base.html.twig not found – add {{ sulu_admin_bar() }} before </body> in your base layout manually.');

        return;
    }

    $content = (string) \file_get_contents($file);

    if (false !== \strpos($content, 'sulu_admin_bar')) {
        output(STATUS_SKIP, 'base.html.twig already contains sulu_admin_bar()');

        return;
    }

    $position = \strrpos($content, '</body>');

    if (false === $position) {
        output(STATUS_WARN, 'base.html.twig: no </body> found – add {{ sulu_admin_bar() }} to your base layout manually.');

        return;
    }

    $content = \substr_replace($content, "    {{ sulu_admin_bar() }}\n", $position, 0);

    \file_put_contents($file, $content);
    output(STATUS_OK, 'base.html.twig: added {{ sulu_admin_bar() }} before </body>');
}

function runCommands(string $projectDir): void
{
    echo "\n";

    runCommand('composer dump-autoload', $projectDir);

    $php = \escapeshellarg(\PHP_BINARY) . ' -d memory_limit=1024M';

    if (\is_file($projectDir . '/bin/console')) {
        runCommand($php . ' bin/console assets:install public', $projectDir);
        runCommand($php . ' bin/console cache:clear', $projectDir);
    } else {
        output(STATUS_WARN, 'bin/console not found – install assets and clear the caches manually.');
    }

    if (\is_file($projectDir . '/bin/websiteconsole')) {
        runCommand($php . ' bin/websiteconsole cache:clear', $projectDir);
    }
}

function runCommand(string $command, string $projectDir): void
{
    echo "$ {$command}\n";

    $previousDir = \getcwd();
    \chdir($projectDir);
    \passthru($command, $exitCode);
    \chdir((string) $previousDir);

    if (0 !== $exitCode) {
        output(STATUS_WARN, "command failed (exit code {$exitCode}): {$command} – run it manually.");

        return;
    }

    output(STATUS_OK, $command);
}
