<?php

declare(strict_types=1);

namespace Xoops\Rector\Support;

use PhpParser\Node\Arg;
use PhpParser\Node\Scalar\String_;

/**
 * Shared SQL-statement classification for the XOOPS 2.7 query()/exec() split.
 *
 * Only a literal string argument can be classified. Anything dynamic (a variable,
 * concatenation, …) is treated as a read so the result stays fetchable and the
 * common SELECT case never breaks — flag those for human review.
 *
 * @license GPL-2.0-or-later
 */
trait SqlKeywordDetector
{
    /**
     * True when the SQL argument is a write/DDL statement (→ exec()), false for a
     * read or an unclassifiable dynamic argument (→ query()).
     */
    private function isWriteSql(?Arg $arg): bool
    {
        if (!$arg instanceof Arg || !$arg->value instanceof String_) {
            return false;
        }

        return (bool) preg_match(
            '/^\s*(INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|TRUNCATE|RENAME|GRANT|REVOKE|LOCK|UNLOCK|CALL|SET)\b/i',
            $arg->value->value
        );
    }
}
