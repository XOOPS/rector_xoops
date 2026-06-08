<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Drops obsolete reference-assignment (`=&`) when the right-hand side is a call
 * or `new` expression:
 *
 *   $x =& foo()           →  $x = foo()
 *   $x =& new ClassName    →  $x = new ClassName
 *   $x =& Class::method()  →  $x = Class::method()
 *   $x =& $obj->method()   →  $x = $obj->method()
 *
 * PHP 7+ deprecates `=& functionCall()`; reference-assignment to a `new` has been
 * pointless since PHP 5 (objects are handles already). Conservative on purpose:
 * we only drop `&` when the RHS is a Call/New/StaticCall — never for `$alias =& $real`,
 * which remains legal and may be intentional.
 *
 * @license GPL-2.0-or-later
 */
final class DropReferenceFromAssignmentRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [AssignRef::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof AssignRef) {
            return null;
        }

        $rhs = $node->expr;
        $isCall = $rhs instanceof FuncCall
            || $rhs instanceof MethodCall
            || $rhs instanceof StaticCall
            || $rhs instanceof New_;

        if (!$isCall) {
            return null;
        }

        // Do NOT copy attributes — origNode would still point to the AssignRef
        // and PhpParser's format-preserving pretty printer would assert
        // class-mismatch on print.
        return new Assign($node->var, $rhs);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Drops obsolete reference-assignment (=&) when RHS is a call or new expression.',
            [new CodeSample(
                <<<'CODE'
$db =& XoopsDatabaseFactory::getDatabaseConnection();
$mod =& xoops_getHandler('module');
$obj =& new MyClass();
CODE,
                <<<'CODE'
$db = XoopsDatabaseFactory::getDatabaseConnection();
$mod = xoops_getHandler('module');
$obj = new MyClass();
CODE
            )]
        );
    }
}
