<?php

declare(strict_types=1);

/**
 * rector-xoops — OPT-IN "risky" rule set.
 *
 * These transforms change runtime BEHAVIOUR (input filtering, escaping, output
 * encoding), unlike the behaviour-preserving default set. Enable deliberately and
 * review every diff:
 *
 *     ->withSets([Xoops\Rector\Set\XoopsSetList::XOOPS, Xoops\Rector\Set\XoopsSetList::XOOPS_RISKY])
 *
 * @license GPL-2.0-or-later
 */

use Rector\Config\RectorConfig;
use Xoops\Rector\Rules\MytsAddSlashesToDbEscapeRector;
use Xoops\Rector\Rules\MytsHtmlSpecialCharsToNativeRector;
use Xoops\Rector\Rules\RemoveDeadMytsStripSlashesRector;
use Xoops\Rector\Rules\ServerSuperglobalToXmfRequestRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rules([
        RemoveDeadMytsStripSlashesRector::class,
        MytsAddSlashesToDbEscapeRector::class,
        MytsHtmlSpecialCharsToNativeRector::class,
    ]);

    // Raw $_SERVER reads → filtered Xmf\Request. Extend the key list as needed.
    $rectorConfig->ruleWithConfiguration(ServerSuperglobalToXmfRequestRector::class, [
        'HTTP_REFERER',
    ]);
};
