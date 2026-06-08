<?php

declare(strict_types=1);

namespace Xoops\Rector\Support;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;

/**
 * Detects a MyTextSanitizer receiver — `$myts` or `$GLOBALS['myts']` — so the
 * MyTS transform rules only fire on the sanitizer, never on an unrelated object
 * that happens to share a method name.
 *
 * @license GPL-2.0-or-later
 */
trait MytsReceiverDetector
{
    private function isMytsReceiver(Expr $var): bool
    {
        if ($var instanceof Variable && $var->name === 'myts') {
            return true;
        }

        return $var instanceof ArrayDimFetch
            && $var->var instanceof Variable
            && $var->var->name === 'GLOBALS'
            && $var->dim instanceof String_
            && $var->dim->value === 'myts';
    }
}
