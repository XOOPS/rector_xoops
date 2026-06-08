<?php

declare(strict_types=1);

/**
 * PHP-CS-Fixer configuration for the rector-xoops package itself.
 *
 * @see https://cs.symfony.com
 */

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/config',
    ])
    ->append([
        __DIR__ . '/rector.php',
        __DIR__ . '/rector-xoops.php',
    ])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12'                       => true,
        '@PSR12:risky'                 => true,
        'array_syntax'                 => ['syntax' => 'short'],
        'binary_operator_spaces'       => ['default' => 'single_space'],
        'concat_space'                 => ['spacing' => 'one'],
        'declare_strict_types'         => true,
        'no_unused_imports'            => true,
        'ordered_imports'              => ['sort_algorithm' => 'alpha'],
        'single_quote'                 => true,
        'trailing_comma_in_multiline'  => true,
    ])
    ->setFinder($finder);
