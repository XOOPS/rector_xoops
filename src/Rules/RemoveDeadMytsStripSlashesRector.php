<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Xoops\Rector\Support\MytsReceiverDetector;

/**
 * Unwraps `$myts->stripSlashesGPC($x)` → `$x` (receiver must be `$myts` /
 * `$GLOBALS['myts']`). Magic-quotes stripping is obsolete on PHP 7.4+.
 *
 * Behaviour-changing in theory (depends on the MyTS build), so it ships in the
 * opt-in `XOOPS_RISKY` set. Only single-argument calls are unwrapped.
 *
 * @license GPL-2.0-or-later
 */
final class RemoveDeadMytsStripSlashesRector extends AbstractRector
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

        if ($node->name->toString() !== 'stripSlashesGPC') {
            return null;
        }

        if (!$this->isMytsReceiver($node->var)) {
            return null;
        }

        $args = $node->getArgs();
        if (count($args) !== 1) {
            return null;
        }

        return $args[0]->value;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Unwraps obsolete $myts->stripSlashesGPC($x) to $x (PHP 7.4+).',
            [new CodeSample(
                '$clean = $myts->stripSlashesGPC($value);',
                '$clean = $value;'
            )]
        );
    }
}
