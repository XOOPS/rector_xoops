<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Rewrites legacy XOOPS kernel include paths that moved in Core27:
 *
 *   class/xoopsobject.php → kernel/object.php
 *   class/xoopsmodule.php → kernel/module.php
 *
 * Keyed on the full path fragment, which is specific enough that it only appears
 * in include/require arguments in practice. Matches with or without a leading
 * slash; leaves everything else untouched.
 *
 * @license GPL-2.0-or-later
 */
final class KernelIncludePathRector extends AbstractRector
{
    private const MAP = [
        'class/xoopsobject.php' => 'kernel/object.php',
        'class/xoopsmodule.php' => 'kernel/module.php',
    ];

    public function getNodeTypes(): array
    {
        return [String_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof String_) {
            return null;
        }

        $new = strtr($node->value, self::MAP);
        if ($new === $node->value) {
            return null;
        }

        return new String_($new);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Rewrites moved XOOPS kernel include paths (class/xoopsobject.php → kernel/object.php).',
            [new CodeSample(
                "require_once XOOPS_ROOT_PATH . '/class/xoopsobject.php';",
                "require_once XOOPS_ROOT_PATH . '/kernel/object.php';"
            )]
        );
    }
}
