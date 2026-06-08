<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Cast\String_ as StringCast;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Fixes the legacy `uniqid(mt_rand(), 1)` idiom for PHP 8 type strictness:
 *
 *   uniqid(mt_rand())     →  uniqid((string) mt_rand())   // prefix must be string
 *   uniqid(…, 1)          →  uniqid(…, true)              // more_entropy is bool
 *
 * Conservative: only casts the prefix when it is literally `mt_rand()`, and only
 * normalises an int-literal `more_entropy` (1/0 → true/false).
 *
 * @license GPL-2.0-or-later
 */
final class UniqidMtRandStrictRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [FuncCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name) {
            return null;
        }
        if ($node->name->toLowerString() !== 'uniqid') {
            return null;
        }

        $args = $node->getArgs();
        $changed = false;

        // Prefix: cast a bare mt_rand() call to (string).
        if (isset($args[0])) {
            $prefix = $args[0]->value;
            if ($prefix instanceof FuncCall
                && $prefix->name instanceof Name
                && $prefix->name->toLowerString() === 'mt_rand') {
                $args[0] = new Arg(new StringCast($prefix));
                $changed = true;
            }
        }

        // more_entropy: int literal 1/0 → true/false.
        if (isset($args[1]) && $args[1]->value instanceof Int_) {
            $value = $args[1]->value->value;
            if ($value === 1 || $value === 0) {
                $args[1] = new Arg(new ConstFetch(new Name($value === 1 ? 'true' : 'false')));
                $changed = true;
            }
        }

        if (!$changed) {
            return null;
        }

        $node->args = $args;

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Casts uniqid(mt_rand()) prefix to string and normalises the more_entropy int literal to bool.',
            [new CodeSample(
                '$id = uniqid(mt_rand(), 1);',
                '$id = uniqid((string) mt_rand(), true);'
            )]
        );
    }
}
