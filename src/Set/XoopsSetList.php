<?php

declare(strict_types=1);

namespace Xoops\Rector\Set;

/**
 * Set list for rector-xoops.
 *
 * Reference from a module's rector.php:
 *
 *     use Xoops\Rector\Set\XoopsSetList;
 *
 *     return RectorConfig::configure()
 *         ->withPaths([__DIR__ . '/class', __DIR__ . '/admin'])
 *         ->withPhpSets(php82: true)        // YOU pick the PHP level
 *         ->withSets([XoopsSetList::XOOPS]); // XOOPS-specific rules
 *
 * @license GPL-2.0-or-later
 */
final class XoopsSetList
{
    /**
     * XOOPS module modernisation rules (DB API renames, mysql_*→xoopsDB,
     * legacy superglobals, reference-assignment cleanup, unserialize hardening,
     * PHP 8.5 string-callable fix, …). Does NOT enable PHP upgrade sets — keep
     * that under the consumer's control via withPhpSets().
     */
    public const XOOPS = __DIR__ . '/../../config/sets/xoops.php';

    /**
     * OPT-IN behaviour-changing rules (input filtering, escaping, output encoding).
     * Enable in addition to XOOPS only when you will review every diff.
     */
    public const XOOPS_RISKY = __DIR__ . '/../../config/sets/xoops-risky.php';
}
