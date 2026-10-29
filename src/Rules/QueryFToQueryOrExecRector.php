<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ClosureUse;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Param;
use PhpParser\Node\StaticVar;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Global_;
use PhpParser\Node\Stmt\Static_;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitor;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Xoops\Rector\Support\SqlKeywordDetector;

/**
 * Replaces the deprecated `$db->queryF($sql, ...)` with the XOOPS 2.7 split:
 * `query()` for reads (SELECT/SHOW/…, returns a fetchable result) and `exec()`
 * for writes/DDL (INSERT/UPDATE/DELETE/…, returns bool).
 *
 * Name-only matching (any receiver), because legacy XOOPS receivers like
 * `$xoopsDB` / `$this->db` usually have no type hint for PHPStan to resolve.
 *
 * The keyword is read from the SQL itself, through concatenation, interpolation
 * and sprintf(), or from every assignment to the `$sql` variable in the same
 * function (or top-level script). When the SQL cannot be classified, or the
 * assignments disagree, the call is left as `queryF()`: it is deprecated, not
 * removed, while `query()` on a write throws at runtime on 2.7.
 *
 * `exec()` needs XOOPS 2.5.12 or later.
 *
 * @license GPL-2.0-or-later
 */
final class QueryFToQueryOrExecRector extends AbstractRector
{
    use SqlKeywordDetector;

    public function getNodeTypes(): array
    {
        // One scope at a time: the script body, then each function body.
        return [FileNode::class, ClassMethod::class, Function_::class, Closure::class];
    }

    public function refactor(Node $node): ?Node
    {
        $stmts = $node instanceof FileNode || $node instanceof ClassMethod
            || $node instanceof Function_ || $node instanceof Closure ? $node->stmts : null;
        if ($stmts === null) {
            return null;
        }

        // Every write to a variable in this scope as [file position, verdict]. A write whose value
        // cannot be read (a parameter, `use`, `global`, `static`, `foreach`, a reference,
        // destructuring) has verdict null.
        /** @var array<string, list<array{int, bool|null}>> $assigned */
        $assigned = [];
        $opaque = $node instanceof FileNode ? [] : array_map(static fn (Param $param): Expr => $param->var, $node->params);
        if ($node instanceof Closure) {
            $opaque = [...$opaque, ...array_map(static fn (ClosureUse $use): Variable => $use->var, $node->uses)];
        }
        foreach ((new NodeFinder())->findInstanceOf($opaque, Variable::class) as $var) {
            if (is_string($var->name)) {
                $assigned[$var->name][] = [-1, null];
            }
        }
        /** @var list<MethodCall> $calls */
        $calls = [];
        $this->traverseNodesWithCallable($stmts, function (Node $inner) use (&$assigned, &$calls): ?int {
            // A reference alias can change the variable anywhere in the scope, so it is unknown
            // whatever the position: `$a =& $sql`, `function () use (&$sql)`.
            $aliased = match (true) {
                $inner instanceof AssignRef => [$inner->var, $inner->expr],
                $inner instanceof Closure => array_map(
                    static fn (ClosureUse $use): Variable => $use->var,
                    array_filter($inner->uses, static fn (ClosureUse $use): bool => $use->byRef),
                ),
                default => [],
            };
            foreach ($aliased as $var) {
                if ($var instanceof Variable && is_string($var->name)) {
                    $assigned[$var->name][] = [-1, null];
                }
            }
            if ($inner instanceof ClassMethod || $inner instanceof Function_ || $inner instanceof Closure
                || $inner instanceof ArrowFunction // binds its own parameters over the outer variables
            ) {
                return NodeVisitor::DONT_TRAVERSE_CHILDREN; // its own scope, refactored separately
            }
            // A variable passed to another call may be taken by reference and rewritten there.
            if ($inner instanceof CallLike && !$inner->isFirstClassCallable() && !$this->isQueryF($inner)) {
                foreach ($inner->getArgs() as $arg) {
                    if ($arg->value instanceof Variable && is_string($arg->value->name)) {
                        $assigned[$arg->value->name][] = [$inner->getStartFilePos(), null];
                    }
                }
            }
            if ($inner instanceof Assign && $inner->var instanceof Variable && is_string($inner->var->name)) {
                $assigned[$inner->var->name][] = [$inner->getStartFilePos(), $this->classifySql($inner->expr)];
            }
            $targets = match (true) {
                $inner instanceof Assign => $inner->var instanceof Variable ? [] : [$inner->var],
                // `.=` appends after the keyword; any other compound operator can rewrite it.
                $inner instanceof AssignOp => $inner instanceof AssignOp\Concat ? [] : [$inner->var],
                $inner instanceof Foreach_ => array_filter([$inner->keyVar, $inner->valueVar]),
                $inner instanceof Global_ => $inner->vars,
                $inner instanceof Static_ => array_map(static fn (StaticVar $var): Variable => $var->var, $inner->vars),
                default => [],
            };
            foreach ((new NodeFinder())->findInstanceOf($targets, Variable::class) as $var) {
                if (is_string($var->name)) {
                    $assigned[$var->name][] = [$inner->getStartFilePos(), null];
                }
            }
            if ($inner instanceof MethodCall && $this->isQueryF($inner)) {
                $calls[] = $inner;
            }

            return null;
        });

        $changed = false;
        foreach ($calls as $call) {
            $isWrite = $this->callIsWrite($call, $assigned);
            if ($isWrite === null) {
                continue;
            }
            $call->name = new Identifier($isWrite ? 'exec' : 'query');
            $changed = true;
        }

        return $changed ? $node : null;
    }

    private function isQueryF(Node $call): bool
    {
        return $call instanceof MethodCall
            && $call->name instanceof Identifier
            && $call->name->toLowerString() === 'queryf';
    }

    /**
     * @param array<string, list<array{int, bool|null}>> $assigned
     */
    private function callIsWrite(MethodCall $call, array $assigned): ?bool
    {
        $arg = $call->args[0] ?? null;
        if (!$arg instanceof Arg || $arg->unpack) {
            return null;
        }
        if (!$arg->value instanceof Variable) {
            return $this->classifySql($arg->value);
        }

        $name = $arg->value->name;
        $at = $call->getStartFilePos();
        $verdicts = [];
        foreach (is_string($name) ? ($assigned[$name] ?? []) : [] as [$position, $verdict]) {
            // Only writes before the call may reach it; a later one may be what a loop runs it with.
            if ($at < 0 || $position >= $at) {
                return null;
            }
            $verdicts[] = $verdict;
        }
        if ($verdicts === [] || in_array(null, $verdicts, true)) {
            return null; // a parameter, a global, or an assignment we cannot read
        }
        foreach ($verdicts as $verdict) {
            if ($verdict !== $verdicts[0]) {
                return null; // reads and writes through the same variable
            }
        }

        return $verdicts[0];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replaces deprecated queryF() with query() (reads) or exec() (writes) per the XOOPS 2.7 split, chosen from the SQL keyword; leaves it when the SQL cannot be classified.',
            [new CodeSample(
                <<<'CODE'
$result = $xoopsDB->queryF("SELECT * FROM x");
$xoopsDB->queryF('DELETE FROM ' . $xoopsDB->prefix('x') . ' WHERE id = 1');
$sql = sprintf('UPDATE %s SET y = 1', $xoopsDB->prefix('x'));
$xoopsDB->queryF($sql);
CODE,
                <<<'CODE'
$result = $xoopsDB->query("SELECT * FROM x");
$xoopsDB->exec('DELETE FROM ' . $xoopsDB->prefix('x') . ' WHERE id = 1');
$sql = sprintf('UPDATE %s SET y = 1', $xoopsDB->prefix('x'));
$xoopsDB->exec($sql);
CODE
            )]
        );
    }
}
