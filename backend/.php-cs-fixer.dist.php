<?php

declare(strict_types=1);

$finder = new PhpCsFixer\Finder()
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor')
    ->exclude('public/build')
    ->exclude('assets/vendor')
    ->notPath('src/Kernel.php')
    ->notPath('public/index.php')
    ->notPath('config/reference.php')
    ->notPath('config/preload.php')
    ->notPath('config/bundles.php')
    ->name('*.php');

return new PhpCsFixer\Config()
    ->setRules([
        '@Symfony' => true,

        'declare_strict_types' => true,
        'yoda_style' => false,
        'concat_space' => ['spacing' => 'one'],

        // Import handling
        'global_namespace_import' => [
            'import_classes' => true,
            'import_functions' => true,
            'import_constants' => true,
        ],
        'no_unused_imports' => true,
        'ordered_imports' => true,
    ])

    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setUnsupportedPhpVersionAllowed(true);
