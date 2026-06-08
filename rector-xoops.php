<?php

declare(strict_types=1);

/**
 * Standalone full-sweep Rector config for XOOPS module modernisation.
 *
 * This is the "run it across a whole XOOPS tree" convenience config. It loads the
 * reusable XOOPS rule set (config/sets/xoops.php) and adds:
 *   - withPhpSets(php84: true) — every PHP upgrade rule 5.2 → 8.4. This is an
 *     AGGRESSIVE sweep: only use it when the target module's PHP floor allows 8.4.
 *     Lower it to php82 (XoopsCore27) or php74 (XoopsCore25) to match your target.
 *   - example paths + a skip list (override the path on the CLI per run)
 *   - a Rector cache directory
 *
 * Modules built from the XOOPS skeleton do NOT use this file — they reference
 * Xoops\Rector\Set\XoopsSetList::XOOPS from their own rector.php and pick their own
 * PHP level. See README.md.
 *
 * Usage (run from anywhere; this config bootstraps the package autoloader below,
 * so it resolves the XOOPS rules even when invoked by a Rector binary installed
 * elsewhere in the tree):
 *
 *   # Dry-run on ONE module first (always):
 *   rector process /path/to/htdocs/modules/songlist --config=rector-xoops.php --dry-run
 *
 *   # Apply when the diff looks sane:
 *   rector process /path/to/htdocs/modules/songlist --config=rector-xoops.php
 *
 * @license GPL-2.0-or-later
 */

use Rector\Config\RectorConfig;

// Bootstrap this package's autoloader so the XOOPS rules + set list resolve even
// when this config is run by a Rector binary installed elsewhere in the tree.
if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Default scan target, but only if it exists — so running with an explicit CLI
// path (rector process <path> --config=rector-xoops.php) works from anywhere.
$defaultPaths = array_filter([getcwd() . '/htdocs/modules'], 'is_dir');

$config = RectorConfig::configure()

    // Directories Rector must NOT touch. The bundled-library / already-modernised
    // entries below are examples from one test bed — adjust for your tree.
    ->withSkip([
        '*/vendor/*',
        '*/node_modules/*',
        '*/.git/*',
        '*/templates_c/*',
        '*/templates/*',     // .tpl files — Smarty, not PHP
        '*/language/*',      // language constants — manual review only
        '*/preloads/*',      // preload handlers — review by hand
        '*/tests/*',

        // --- examples: bundled third-party libs / already-done modules ---
        // '*/extgallery/class/pear/*',
        // '*/realestate/*',
        // '*/_OLD/*',
    ])

    ->withCache(getcwd() . '/.rector-cache')

    // Bring in every PHP upgrade rule through 8.4 for the legacy sweep.
    ->withPhpSets(php84: true)

    // The reusable XOOPS rule set (DB renames, mysql_*→xoopsDB, superglobals, …).
    ->withSets([__DIR__ . '/config/sets/xoops.php']);

if ($defaultPaths !== []) {
    $config->withPaths($defaultPaths);
}

return $config;
