<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * `mysql_select_db($name)` → `mysqli_select_db($GLOBALS['xoopsDB']->conn, $name)`.
 *
 * `mysql_select_db()` was removed in PHP 7; `mysqli_select_db()` needs the XOOPS
 * connection as its first argument. Behaviour is preserved (rare in module code,
 * but a hard fatal when present).
 *
 * @license GPL-2.0-or-later
 */
final class MysqlSelectDbToXoopsDbRector extends AbstractRector
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

        if ($node->name->toLowerString() !== 'mysql_select_db') {
            return null;
        }

        $conn = new PropertyFetch(
            new ArrayDimFetch(new Variable('GLOBALS'), new String_('xoopsDB')),
            new Identifier('conn')
        );

        return new FuncCall(
            new Name('mysqli_select_db'),
            [new Arg($conn), ...$node->getArgs()]
        );
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Replaces removed mysql_select_db() with mysqli_select_db(\$GLOBALS['xoopsDB']->conn, …).",
            [new CodeSample(
                'mysql_select_db($dbName);',
                "mysqli_select_db(\$GLOBALS['xoopsDB']->conn, \$dbName);"
            )]
        );
    }
}
