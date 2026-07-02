<?php

declare(strict_types=1);

/*
 * This file is part of the AdminBarBundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/*
 * Locates the composer autoloader: the bundle's own vendor/ (standalone
 * checkout) or, as a fallback, the autoloader of a surrounding project
 * the bundle is embedded in (e.g. <project>/bundles/AdminBarBundle).
 */

$candidates = [
    \dirname(__DIR__) . '/vendor/autoload.php',
    \dirname(__DIR__, 3) . '/vendor/autoload.php',
    \dirname(__DIR__, 4) . '/vendor/autoload.php',
];

$loader = null;
foreach ($candidates as $candidate) {
    if (\is_file($candidate)) {
        $loader = require $candidate;

        break;
    }
}

if (null === $loader) {
    echo "Composer autoloader not found. Run \"composer install\" first.\n";
    exit(1);
}

// Make the bundle and test classes resolvable even when running against a
// surrounding project's autoloader that does not know this package.
if ($loader instanceof \Composer\Autoload\ClassLoader) {
    $loader->addPsr4('Elazhari\\SuluAdminBarBundle\\', \dirname(__DIR__) . '/src');
    $loader->addPsr4('Elazhari\\SuluAdminBarBundle\\Tests\\', __DIR__);
}
