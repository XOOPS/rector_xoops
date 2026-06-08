<?php

declare(strict_types=1);

/**
 * Rector config for the rector-xoops package's OWN code.
 *
 * Deliberately does NOT apply the XOOPS modernisation set to itself — this is a
 * tool, not a XOOPS module. Just keep our own code current and tidy.
 *
 * @license GPL-2.0-or-later
 */

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withCache(__DIR__ . '/.build/rector')
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/config',
    ])
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        earlyReturn: true,
    );
