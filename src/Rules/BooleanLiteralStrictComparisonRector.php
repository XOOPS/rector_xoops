<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\ConstFetch;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Forces strict comparison for boolean and null literals:
 *
 *   $x == true   →  $x === true
 *   $x == false  →  $x === false
 *   $x == null   →  $x === null
 *   $x != true   →  $x !== true
 *
 * ...and the mirrored forms (true == $x, etc.). Catches the most common
 * loose-comparison mistakes in legacy XOOPS code.
 *
 * @license GPL-2.0-or-later
 */
final class BooleanLiteralStrictComparisonRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Equal::class, NotEqual::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Equal && !$node instanceof NotEqual) {
            return null;
        }

        if (!$this->isBoolOrNullLiteral($node->left) && !$this->isBoolOrNullLiteral($node->right)) {
            return null;
        }

        // Do NOT copy attributes — origNode would still point to Equal/NotEqual
        // and PhpParser's format-preserving pretty printer would assert mismatch.
        return $node instanceof Equal
            ? new Identical($node->left, $node->right)
            : new NotIdentical($node->left, $node->right);
    }

    private function isBoolOrNullLiteral(Node $node): bool
    {
        if (!$node instanceof ConstFetch) {
            return false;
        }

        $name = strtolower((string) $node->name);

        return in_array($name, ['true', 'false', 'null'], true);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Forces strict comparison (===, !==) when comparing against true/false/null literals.',
            [new CodeSample(
                <<<'CODE'
if ($result == true) {}
if ($value != null) {}
if (false == $flag) {}
CODE,
                <<<'CODE'
if ($result === true) {}
if ($value !== null) {}
if (false === $flag) {}
CODE
            )]
        );
    }
}
