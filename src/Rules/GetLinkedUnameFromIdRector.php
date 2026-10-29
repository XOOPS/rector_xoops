<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Inlines the removed `xoops_getLinkedUnameFromId($uid)` as its 2.5 body did:
 * `\XoopsUserUtility::getUnameFromId($uid, false, true)` (the linked name), preceded by
 * `\xoops_load('XoopsUserUtility');`, since the core loads that class only on demand.
 *
 * The load must run exactly where the call did, so only a call that IS the statement's value
 * is rewritten: the statement itself, the right side of an assignment, a single `echo` argument or
 * the `return` value. A call inside a condition, ternary or larger expression is left alone.
 *
 * @license GPL-2.0-or-later
 */
final class GetLinkedUnameFromIdRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Expression::class, Return_::class, Echo_::class];
    }

    /**
     * @return Stmt[]|null
     */
    public function refactor(Node $node): ?array
    {
        $changed = false;
        if ($node instanceof Expression) {
            if ($node->expr instanceof Assign) {
                $node->expr->expr = $this->inline($node->expr->expr, $changed);
            } else {
                $node->expr = $this->inline($node->expr, $changed);
            }
        } elseif ($node instanceof Return_ && $node->expr instanceof Expr) {
            $node->expr = $this->inline($node->expr, $changed);
        } elseif ($node instanceof Echo_ && count($node->exprs) === 1) {
            // With several arguments the earlier ones would run after the inserted load.
            $node->exprs[0] = $this->inline($node->exprs[0], $changed);
        }
        if (!$changed || !$node instanceof Stmt) {
            return null;
        }

        return [
            new Expression(new FuncCall(new FullyQualified('xoops_load'), [new Arg(new String_('XoopsUserUtility'))])),
            $node,
        ];
    }

    private function inline(Expr $expr, bool &$changed): Expr
    {
        if (!$expr instanceof FuncCall
            || !$this->isName($expr, 'xoops_getLinkedUnameFromId')
            || count($expr->args) !== 1
            || !$expr->args[0] instanceof Arg
            || $expr->args[0]->name !== null
            || $expr->args[0]->unpack
            // The inserted load runs before the argument now: a call in it could observe that.
            || (new NodeFinder())->findFirstInstanceOf($expr->args[0]->value, CallLike::class) instanceof CallLike
        ) {
            return $expr;
        }
        $changed = true;

        return new StaticCall(new FullyQualified('XoopsUserUtility'), 'getUnameFromId', [
            $expr->args[0],
            new Arg(new ConstFetch(new Name('false'))),
            new Arg(new ConstFetch(new Name('true'))),
        ]);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Inlines the removed xoops_getLinkedUnameFromId() as XoopsUserUtility::getUnameFromId($uid, false, true).',
            [new CodeSample(
                '$poster = xoops_getLinkedUnameFromId($uid);',
                "\\xoops_load('XoopsUserUtility');\n\$poster = \\XoopsUserUtility::getUnameFromId(\$uid, false, true);"
            )]
        );
    }
}
