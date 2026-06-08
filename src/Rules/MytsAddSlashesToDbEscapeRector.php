<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Xoops\Rector\Support\MytsReceiverDetector;

/**
 * `$myts->addSlashes($x)` → `$GLOBALS['xoopsDB']->escape($x)` (receiver must be
 * `$myts` / `$GLOBALS['myts']`). SQL escaping belongs on the DB layer, not the
 * text sanitizer.
 *
 * Behaviour-changing (and only correct in a SQL context), so it ships in the
 * opt-in `XOOPS_RISKY` set. Prefer Criteria / prepared values where possible.
 *
 * @license GPL-2.0-or-later
 */
final class MytsAddSlashesToDbEscapeRector extends AbstractRector
{
    use MytsReceiverDetector;

    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return null;
        }

        if ($node->name->toString() !== 'addSlashes') {
            return null;
        }

        if (!$this->isMytsReceiver($node->var)) {
            return null;
        }

        $xoopsDb = new ArrayDimFetch(new Variable('GLOBALS'), new String_('xoopsDB'));

        return new MethodCall($xoopsDb, new Identifier('escape'), $node->getArgs());
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Rewrites \$myts->addSlashes(\$x) to \$GLOBALS['xoopsDB']->escape(\$x).",
            [new CodeSample(
                '$sql = "... " . $myts->addSlashes($value);',
                '$sql = "... " . $GLOBALS[\'xoopsDB\']->escape($value);'
            )]
        );
    }
}
