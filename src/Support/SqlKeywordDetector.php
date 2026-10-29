<?php

declare(strict_types=1);

namespace Xoops\Rector\Support;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;

/**
 * Shared SQL-statement classification for the XOOPS 2.7 query()/exec() split.
 *
 * The statement keyword is read from the leading literal text of the SQL: a plain
 * string, the first part of an interpolated string ("DELETE FROM {$t}"), the left
 * end of a concatenation ("DELETE FROM " . $db->prefix('x')), or a sprintf()
 * format. The read set is the core's own (`mysqldatabase.php`: SELECT, WITH,
 * SHOW, DESCRIBE, EXPLAIN); every other keyword is a write, exactly as the core
 * decides at runtime.
 *
 * @license GPL-2.0-or-later
 */
trait SqlKeywordDetector
{
    /**
     * True when the SQL argument is a write/DDL statement (→ exec()), false for a
     * read or an unclassifiable argument (→ query()). For callers that must pick
     * one; prefer classifySql() where leaving the call alone is an option.
     */
    private function isWriteSql(?Arg $arg): bool
    {
        return $arg instanceof Arg && $this->classifySql($arg->value) === true;
    }

    /**
     * @return bool|null true = write/DDL, false = read, null = no leading keyword to read
     */
    private function classifySql(Expr $sql): ?bool
    {
        return $this->classifySqlPrefix($sql, true);
    }

    /**
     * @param bool $complete whether the text ends where the SQL ends; a literal followed by
     *                       more SQL may stop inside the keyword ('S' . 'ELECT …')
     */
    private function classifySqlPrefix(Expr $sql, bool $complete): ?bool
    {
        if ($sql instanceof String_) {
            return $this->classifySqlText($sql->value, $complete);
        }
        if ($sql instanceof InterpolatedString) {
            $first = $sql->parts[0] ?? null;

            return $first instanceof InterpolatedStringPart
                ? $this->classifySqlText($first->value, $complete && count($sql->parts) === 1)
                : null;
        }
        if ($sql instanceof Concat) {
            return $this->classifySqlPrefix($sql->left, false);
        }
        if ($sql instanceof FuncCall
            && $sql->name instanceof Name
            && $sql->name->toLowerString() === 'sprintf'
            && ($sql->args[0] ?? null) instanceof Arg
        ) {
            return $this->classifySqlPrefix($sql->args[0]->value, $complete);
        }

        return null;
    }

    private function classifySqlText(string $sql, bool $complete): ?bool
    {
        // The keyword must visibly end: at a non-word character, or at the end of the whole SQL
        // ('%' too: a sprintf placeholder can carry the rest of the keyword).
        $end = $complete ? '(?![A-Za-z0-9_%])' : '(?=[^A-Za-z0-9_%])';
        if (preg_match('/^\s*(?:SELECT|WITH|SHOW|DESCRIBE|EXPLAIN)' . $end . '/i', $sql) === 1) {
            return false;
        }

        // Any other leading keyword is a statement the core runs through exec().
        return preg_match('/^\s*[A-Za-z]+' . $end . '/', $sql) === 1 ? true : null;
    }
}
