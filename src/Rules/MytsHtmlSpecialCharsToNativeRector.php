<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\BinaryOp\BitwiseOr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Xoops\Rector\Support\MytsReceiverDetector;

/**
 * `$myts->htmlSpecialChars($x)` → `htmlspecialchars($x, ENT_QUOTES | ENT_SUBSTITUTE, _CHARSET)`
 * (receiver must be `$myts` / `$GLOBALS['myts']`).
 *
 * Behaviour-changing (MyTS may treat existing entities differently), so it ships
 * in the opt-in `XOOPS_RISKY` set. Only single-argument calls are converted;
 * review output for double-encoding.
 *
 * @license GPL-2.0-or-later
 */
final class MytsHtmlSpecialCharsToNativeRector extends AbstractRector
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

        if ($node->name->toString() !== 'htmlSpecialChars') {
            return null;
        }

        if (!$this->isMytsReceiver($node->var)) {
            return null;
        }

        $args = $node->getArgs();
        if (count($args) !== 1) {
            return null;
        }

        $flags = new BitwiseOr(
            new ConstFetch(new Name('ENT_QUOTES')),
            new ConstFetch(new Name('ENT_SUBSTITUTE'))
        );

        return new FuncCall(new Name('htmlspecialchars'), [
            $args[0],
            new Arg($flags),
            new Arg(new ConstFetch(new Name('_CHARSET'))),
        ]);
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Rewrites $myts->htmlSpecialChars($x) to htmlspecialchars($x, ENT_QUOTES | ENT_SUBSTITUTE, _CHARSET).',
            [new CodeSample(
                '$out = $myts->htmlSpecialChars($value);',
                '$out = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, _CHARSET);'
            )]
        );
    }
}
