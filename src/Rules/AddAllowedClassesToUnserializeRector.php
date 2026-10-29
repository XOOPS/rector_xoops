<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\ClosureUse;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Global_;
use PhpParser\Node\Stmt\Static_;
use PhpParser\Node\Stmt\Unset_;
use PhpParser\NodeFinder;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use SplObjectStorage;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Adds `['allowed_classes' => false]` to a single-argument `unserialize()` call
 * whose result provably behaves the same without objects — PHP object-injection hardening
 * that cannot break anything.
 *
 * Many XOOPS modules serialize objects on purpose (a cached `CriteriaCompo`, a
 * stored `XoopsObject`, an array of them). With `allowed_classes => false` those
 * come back as `__PHP_Incomplete_Class` and fail at runtime. An array-looking use
 * is no proof: `foreach`, `$x[...]`, `json_encode()` hand on elements that can be
 * objects, and `count()`, `isset($x['k'])` and `array_keys()` also accept a
 * serialized `ArrayObject` / `ArrayAccess` object (or compare elements). So a call
 * is rewritten only when every use behaves the same for an incomplete object:
 *
 *  - ARRAY:   an `is_array()` argument
 *  - NEUTRAL: a truthiness test, `isset` / `empty` / `unset`, a comparison with
 *             `false` / `null` / `true` / `[]`
 *  - anything else (`foreach`, `$x[...]`, `count()`, `->`, return, another call)
 *    leaves the call alone, as does a named or unpacked argument.
 *
 * At least one ARRAY use is required. A result assigned to a variable is followed
 * through that variable's later reads in the same function (or script).
 *
 * @license GPL-2.0-or-later
 */
final class AddAllowedClassesToUnserializeRector extends AbstractRector
{
    /** Functions that give the same answer for an array and for any object, complete or not. */
    private const ARRAY_FUNCTIONS = ['is_array'];

    private const ARRAY = 'array';
    private const NEUTRAL = 'neutral';
    private const UNKNOWN = 'unknown';

    /** @var SplObjectStorage<Node, Node> */
    private SplObjectStorage $parents;

    /** @var array<string, true> variables another name can reach (`=&`, `&$param`, `use (&$x)`, `global`, `static`) */
    private array $aliased = [];

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

        $this->parents = new SplObjectStorage();
        $this->aliased = [];
        $finder = new NodeFinder();
        $byRefUses = static fn (Closure $c): array => array_values(array_filter($c->uses, static fn (ClosureUse $u): bool => $u->byRef));
        $exposed = $node instanceof FileNode ? [] : array_values(array_filter($node->params, static fn (Param $p): bool => $p->byRef));
        if ($node instanceof Closure) {
            $exposed = [...$exposed, ...$byRefUses($node)];
        }
        foreach ($finder->find($stmts, static fn (Node $n): bool => $n instanceof AssignRef
            || $n instanceof Closure || $n instanceof Global_ || $n instanceof Static_) as $found) {
            $exposed = [...$exposed, ...($found instanceof Closure ? $byRefUses($found) : [$found])];
        }
        foreach ($finder->findInstanceOf($exposed, Variable::class) as $var) {
            if (is_string($var->name)) {
                $this->aliased[$var->name] = true;
            }
        }
        /** @var list<FuncCall> $calls */
        $calls = [];
        /** @var list<Variable> $variables */
        $variables = [];
        $this->index($stmts, $node, $calls, $variables);

        $changed = false;
        foreach ($calls as $call) {
            $kinds = [];
            $this->classify($call, $kinds, 0, $variables);
            if (!in_array(self::ARRAY, $kinds, true) || in_array(self::UNKNOWN, $kinds, true)) {
                continue;
            }
            $call->args[] = new Arg(new Array_([
                new ArrayItem(new ConstFetch(new Name('false')), new String_('allowed_classes')),
            ]));
            $changed = true;
        }

        return $changed ? $node : null;
    }

    /**
     * Records each node's parent, the single-argument unserialize() calls and the
     * variables of this scope; nested functions are their own scope.
     *
     * @param Node|array<mixed> $subject
     * @param list<FuncCall>    $calls
     * @param list<Variable>    $variables
     */
    private function index(Node|array $subject, Node $parent, array &$calls, array &$variables): void
    {
        if (is_array($subject)) {
            foreach ($subject as $child) {
                if ($child instanceof Node || is_array($child)) {
                    $this->index($child, $parent, $calls, $variables);
                }
            }

            return;
        }

        $this->parents[$subject] = $parent;
        if ($subject instanceof Closure) {
            // `use ($data)` reads this scope's variable; the body is the closure's own scope.
            $this->index($subject->uses, $subject, $calls, $variables);

            return;
        }
        if ($subject instanceof ClassMethod || $subject instanceof Function_) {
            return;
        }
        if ($subject instanceof FuncCall
            && $subject->name instanceof Name
            && $subject->name->toLowerString() === 'unserialize'
            && count($subject->args) === 1
            && $subject->args[0] instanceof Arg
            && $subject->args[0]->name === null // a positional option after a named argument is invalid PHP
            && !$subject->args[0]->unpack
        ) {
            $calls[] = $subject;
        }
        if ($subject instanceof Variable && is_string($subject->name)) {
            $variables[] = $subject;
        }
        foreach ($subject->getSubNodeNames() as $name) {
            $child = $subject->{$name};
            if ($child instanceof Node || is_array($child)) {
                $this->index($child, $subject, $calls, $variables);
            }
        }
    }

    /**
     * @param list<string>   $kinds
     * @param list<Variable> $variables
     */
    private function classify(Expr $value, array &$kinds, int $depth, array $variables): void
    {
        $parent = $this->parents[$value] ?? null;

        if ($parent instanceof Arg) {
            $call = $this->parents[$parent] ?? null;
            $name = $call instanceof FuncCall && $call->name instanceof Name ? $call->name->toLowerString() : '';
            $kinds[] = in_array($name, self::ARRAY_FUNCTIONS, true) ? self::ARRAY : self::UNKNOWN;
        } elseif ($parent instanceof BooleanNot || $parent instanceof Isset_
            || $parent instanceof Empty_ || $parent instanceof Unset_
            || $parent instanceof BinaryOp\BooleanAnd || $parent instanceof BinaryOp\BooleanOr
            || $parent instanceof BinaryOp\LogicalAnd || $parent instanceof BinaryOp\LogicalOr
            || $parent instanceof BinaryOp\LogicalXor
        ) {
            $kinds[] = self::NEUTRAL;
        } elseif ($parent instanceof BinaryOp\Identical || $parent instanceof BinaryOp\NotIdentical
            || $parent instanceof BinaryOp\Equal || $parent instanceof BinaryOp\NotEqual
        ) {
            $other = $parent->left === $value ? $parent->right : $parent->left;
            $kinds[] = $this->isNeutralLiteral($other) ? self::NEUTRAL : self::UNKNOWN;
        } elseif ($parent instanceof BinaryOp\Coalesce && $parent->left === $value) {
            $this->classify($parent, $kinds, $depth, $variables); // `$v ?? []` flows on
        } elseif ($parent instanceof Ternary && $parent->cond === $value) {
            if ($parent->if === null) {
                $this->classify($parent, $kinds, $depth, $variables); // `$v ?: []` flows on
            } else {
                $kinds[] = self::NEUTRAL;
            }
        } elseif ($parent instanceof Stmt\If_ || $parent instanceof Stmt\ElseIf_
            || $parent instanceof Stmt\While_ || $parent instanceof Stmt\Do_
        ) {
            $kinds[] = $parent->cond === $value ? self::NEUTRAL : self::UNKNOWN;
        } elseif ($parent instanceof Assign && $parent->expr === $value
            && $parent->var instanceof Variable && is_string($parent->var->name) && $depth === 0
            // The assigned value must not flow on (`$copy = $x = unserialize(…)`), nor the
            // variable be reachable under another name.
            && ($this->parents[$parent] ?? null) instanceof Stmt\Expression
            && !isset($this->aliased[$parent->var->name])
        ) {
            $uses = $this->laterReads($parent->var->name, $parent, $variables);
            if ($uses === []) {
                $kinds[] = self::UNKNOWN;
            }
            foreach ($uses as $use) {
                $this->classify($use, $kinds, $depth + 1, $variables);
            }
        } else {
            $kinds[] = self::UNKNOWN;
        }
    }

    /**
     * Reads of `$name` after the assignment in this scope; a later assignment target is not a read.
     * Inside a loop, a read earlier in the body sees the value on the next iteration, so every
     * read from the start of the outermost enclosing loop counts.
     *
     * @param list<Variable> $variables
     *
     * @return list<Variable>
     */
    private function laterReads(string $name, Assign $assign, array $variables): array
    {
        $after = $assign->getEndFilePos();
        for ($p = $this->parents[$assign] ?? null; $p !== null; $p = $this->parents[$p] ?? null) {
            if ($p instanceof Stmt\For_ || $p instanceof Stmt\Foreach_
                || $p instanceof Stmt\While_ || $p instanceof Stmt\Do_
            ) {
                $after = min($after, $p->getStartFilePos() - 1);
            }
        }
        if ($after < 0) {
            return []; // a node built by another rule this run: no position to compare
        }
        $reads = [];
        foreach ($variables as $variable) {
            if ($variable->name !== $name || $variable->getStartFilePos() <= $after) {
                continue;
            }
            $parent = $this->parents[$variable] ?? null;
            if ($parent instanceof Assign && $parent->var === $variable) {
                continue;
            }
            $reads[] = $variable;
        }

        return $reads;
    }

    private function isNeutralLiteral(Expr $expr): bool
    {
        if ($expr instanceof ConstFetch) {
            return in_array($expr->name->toLowerString(), ['false', 'null', 'true'], true);
        }

        return $expr instanceof Array_ && $expr->items === [];
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Hardens unserialize() against PHP object injection by adding ['allowed_classes' => false] where the result provably behaves the same without objects.",
            [new CodeSample(
                <<<'CODE'
$data = unserialize($payload);
$valid = is_array($data);
$item = unserialize($cached);
return $item->getVar('id');
CODE,
                <<<'CODE'
$data = unserialize($payload, ['allowed_classes' => false]);
$valid = is_array($data);
$item = unserialize($cached);
return $item->getVar('id');
CODE
            )]
        );
    }
}
