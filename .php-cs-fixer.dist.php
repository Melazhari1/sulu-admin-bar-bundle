<?php

declare(strict_types=1);

$header = <<<'HEADER'
This file is part of the AdminBarBundle.

This source file is subject to the MIT license that is bundled
with this source code in the file LICENSE.
HEADER;

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->append([__DIR__ . '/install.php']);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'declare_strict_types' => true,
        'header_comment' => ['header' => $header],
        'native_function_invocation' => ['include' => ['@all']],
        'concat_space' => ['spacing' => 'one'],
        'yoda_style' => true,
    ])
    ->setFinder($finder);
