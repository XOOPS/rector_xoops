<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Type\ObjectType;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Rewrites core include paths whose `class/` file is a deprecated stub (since 2.5.4)
 * that only includes its replacement:
 *
 *   class/xoopsobject.php    → kernel/object.php
 *   class/xoopsmodule.php    → kernel/module.php
 *   class/xoopsuser.php      → kernel/user.php
 *   class/mimetypes.inc.php  → include/mimetypes.inc.php
 *
 * Only a core-rooted path is rewritten — `XOOPS_ROOT_PATH . '/class/…'` or
 * `$xoops->path('class/…')` — because a module may ship its own `class/xoopsuser.php`
 * under `__DIR__`. `class/errorhandler.php` is not in the map: that stub still
 * declares XoopsErrorHandler.
 *
 * @license GPL-2.0-or-later
 */
final class KernelIncludePathRector extends AbstractRector
{
    private const MAP = [
        'class/xoopsobject.php' => 'kernel/object.php',
        'class/xoopsmodule.php' => 'kernel/module.php',
        'class/xoopsuser.php' => 'kernel/user.php',
        'class/mimetypes.inc.php' => 'include/mimetypes.inc.php',
    ];

    public function getNodeTypes(): array
    {
        return [Concat::class, MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Concat
            && $node->left instanceof ConstFetch
            && $node->left->name->toString() === 'XOOPS_ROOT_PATH'
            && $node->right instanceof String_
        ) {
            $new = $this->rewrite($node->right);
            if ($new === null) {
                return null;
            }
            $node->right = $new;

            return $node;
        }

        if ($node instanceof MethodCall
            && $node->name instanceof Identifier
            && $node->name->toLowerString() === 'path'
            && $this->isXoopsKernel($node->var)
            && ($node->args[0] ?? null) instanceof Arg
            && $node->args[0]->value instanceof String_
        ) {
            $new = $this->rewrite($node->args[0]->value);
            if ($new === null) {
                return null;
            }
            $node->args[0]->value = $new;

            return $node;
        }

        return null;
    }

    /**
     * The core kernel object: `$xoops`, `$GLOBALS['xoops']`, `\Xoops::getInstance()` or a
     * receiver typed as it. Any other `->path()` (a plugin's, a module helper's) is not core-rooted.
     */
    private function isXoopsKernel(Expr $receiver): bool
    {
        if ($receiver instanceof Variable) {
            return $receiver->name === 'xoops';
        }
        if ($receiver instanceof ArrayDimFetch) {
            return $receiver->var instanceof Variable && $receiver->var->name === 'GLOBALS'
                && $receiver->dim instanceof String_ && $receiver->dim->value === 'xoops';
        }
        if ($receiver instanceof StaticCall) {
            return $this->isName($receiver->class, 'Xoops') && $this->isName($receiver->name, 'getInstance');
        }

        return $this->isObjectType($receiver, new ObjectType('xos_kernel_Xoops2'));
    }

    private function rewrite(String_ $path): ?String_
    {
        $target = self::MAP[ltrim($path->value, '/')] ?? null;
        if ($target === null) {
            return null;
        }
        $slash = str_starts_with($path->value, '/') ? '/' : '';

        return new String_($slash . $target, ['kind' => $path->getAttribute('kind', String_::KIND_SINGLE_QUOTED)]);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Rewrites core include paths of deprecated class/ stubs (class/xoopsobject.php → kernel/object.php, …).',
            [new CodeSample(
                "require_once XOOPS_ROOT_PATH . '/class/xoopsobject.php';",
                "require_once XOOPS_ROOT_PATH . '/kernel/object.php';"
            )]
        );
    }
}
