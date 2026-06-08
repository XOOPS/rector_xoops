<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeVisitor;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Removes statement-level function calls whose name appears in the configured list.
 * Used to drop deprecated no-op calls:
 *   - mt_srand(...)     — PHP 7.1+ auto-seeds Mersenne Twister
 *   - imagedestroy(...) — PHP 8.0+ GdImage objects are GC'd; deprecated in 8.5
 *
 * Configure with lowercase function names:
 *   ->withConfiguredRule(RemoveFuncCallRector::class, ['mt_srand', 'imagedestroy'])
 *
 * SAFETY: only removes calls used as standalone statements (Expression). A nested
 * call that contributes to an expression is left alone.
 *
 * @license GPL-2.0-or-later
 */
final class RemoveFuncCallRector extends AbstractRector implements ConfigurableRectorInterface
{
    /** @var list<string> */
    private array $functionNames = [];

    /** @param mixed $configuration */
    public function configure($configuration): void
    {
        if (!is_array($configuration)) {
            return;
        }
        $this->functionNames = array_map('strtolower', array_values(array_filter($configuration, 'is_string')));
    }

    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): int|null
    {
        if (!$node instanceof Expression || !$node->expr instanceof FuncCall) {
            return null;
        }

        $call = $node->expr;
        if (!$call->name instanceof Name) {
            return null;
        }

        if (!in_array($call->name->toLowerString(), $this->functionNames, true)) {
            return null;
        }

        // Signal node removal to the traverser. Return type widened from ?Node
        // because REMOVE_NODE is an int sentinel.
        return NodeVisitor::REMOVE_NODE;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Removes statement-level calls to configured functions (e.g. deprecated/no-op).',
            [new ConfiguredCodeSample(
                <<<'CODE'
mt_srand();
$img = imagecreatefromjpeg($path);
imagedestroy($img);
CODE,
                <<<'CODE'
$img = imagecreatefromjpeg($path);
CODE,
                ['mt_srand', 'imagedestroy']
            )]
        );
    }
}
