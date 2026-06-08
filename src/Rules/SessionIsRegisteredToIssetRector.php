<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * `session_is_registered($name)` → `isset($_SESSION[$name])`.
 *
 * `session_is_registered()` was removed in PHP 8. The argument expression is
 * preserved as the `$_SESSION` key.
 *
 * @license GPL-2.0-or-later
 */
final class SessionIsRegisteredToIssetRector extends AbstractRector
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

        if ($node->name->toLowerString() !== 'session_is_registered') {
            return null;
        }

        $args = $node->getArgs();
        if ($args === []) {
            return null;
        }

        return new Isset_([
            new ArrayDimFetch(new Variable('_SESSION'), $args[0]->value),
        ]);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replaces removed session_is_registered($k) with isset($_SESSION[$k]).',
            [new CodeSample(
                "if (session_is_registered('user')) {}",
                "if (isset(\$_SESSION['user'])) {}"
            )]
        );
    }
}
