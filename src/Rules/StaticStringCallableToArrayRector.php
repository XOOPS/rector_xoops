<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * PHP 8.5 deprecates string callables containing static::, self::, or parent::.
 * Convert to the equivalent array form:
 *
 *   'static::foo'  →  [static::class, 'foo']
 *   'self::foo'    →  [self::class, 'foo']
 *   'parent::foo'  →  [parent::class, 'foo']
 *
 * Only rewrites string literals matching this exact shape — does NOT touch
 * 'ClassName::method' (those resolve fine via class-name lookup).
 *
 * @license GPL-2.0-or-later
 */
final class StaticStringCallableToArrayRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [String_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof String_) {
            return null;
        }

        if (!preg_match('/^(static|self|parent)::([A-Za-z_][A-Za-z0-9_]*)$/', $node->value, $m)) {
            return null;
        }

        return new Array_([
            new ArrayItem(new ClassConstFetch(new Name($m[1]), 'class')),
            new ArrayItem(new String_($m[2])),
        ]);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Converts deprecated 'static::method' / 'self::method' string callables to array form (PHP 8.5).",
            [new CodeSample(
                "array_filter(\$items, 'static::nonEmpty');",
                "array_filter(\$items, [static::class, 'nonEmpty']);"
            )]
        );
    }
}
