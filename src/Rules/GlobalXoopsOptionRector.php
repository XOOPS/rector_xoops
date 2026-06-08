<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Rewrites array access on the local `$xoopsOption` to the `$GLOBALS['xoopsOption']`
 * superglobal — e.g. `$xoopsOption['template_main'] = 'x.tpl'` →
 * `$GLOBALS['xoopsOption']['template_main'] = 'x.tpl'`.
 *
 * On XoopsCore27 a module must set the template via the global; assigning a local
 * `$xoopsOption` silently has no effect. Scoped to `$xoopsOption[...]` accesses only.
 *
 * @license GPL-2.0-or-later
 */
final class GlobalXoopsOptionRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [ArrayDimFetch::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof ArrayDimFetch) {
            return null;
        }

        if (!$node->var instanceof Variable || $node->var->name !== 'xoopsOption') {
            return null;
        }

        $node->var = new ArrayDimFetch(new Variable('GLOBALS'), new String_('xoopsOption'));

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Rewrites \$xoopsOption[...] to \$GLOBALS['xoopsOption'][...] (Core27 template option).",
            [new CodeSample(
                "\$xoopsOption['template_main'] = 'mymodule_index.tpl';",
                "\$GLOBALS['xoopsOption']['template_main'] = 'mymodule_index.tpl';"
            )]
        );
    }
}
