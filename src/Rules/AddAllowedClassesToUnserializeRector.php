<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Adds `['allowed_classes' => false]` as a 2nd argument to single-arg
 * `unserialize()` calls — PHP object-injection hardening.
 *
 * @license GPL-2.0-or-later
 */
final class AddAllowedClassesToUnserializeRector extends AbstractRector
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

        if ($node->name->toLowerString() !== 'unserialize') {
            return null;
        }

        if (count($node->args) !== 1) {
            return null;  // already has options arg, leave alone
        }

        $optionsArray = new Array_([
            new ArrayItem(
                new ConstFetch(new Name('false')),
                new String_('allowed_classes')
            ),
        ]);

        $node->args[] = new Arg($optionsArray);

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Hardens unserialize() against PHP object injection by adding ['allowed_classes' => false].",
            [new CodeSample(
                '$data = unserialize($payload);',
                "\$data = unserialize(\$payload, ['allowed_classes' => false]);"
            )]
        );
    }
}
