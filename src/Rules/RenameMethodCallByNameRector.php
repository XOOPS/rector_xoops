<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Renames `$x->oldName(...)` to `$x->newName(...)` regardless of the static type of
 * the receiver. Built-in RenameMethodRector needs PHPStan to resolve the receiver's
 * class — and in legacy XOOPS code where `$xoopsDB` / `$this->db` have no type hints,
 * that resolution fails and the rename is silently skipped.
 *
 * This rule matches by name only, so it WILL rename a method call on any object that
 * uses the same method name. Pick XOOPS-specific names (queryF, quoteString) to keep
 * collision risk low.
 *
 *   ->withConfiguredRule(RenameMethodCallByNameRector::class, [
 *       'queryF'      => 'exec',
 *       'quoteString' => 'quote',
 *   ])
 *
 * @license GPL-2.0-or-later
 */
final class RenameMethodCallByNameRector extends AbstractRector implements ConfigurableRectorInterface
{
    /** @var array<string, string> */
    private array $renameMap = [];

    /** @param mixed $configuration */
    public function configure($configuration): void
    {
        if (!is_array($configuration)) {
            return;
        }
        $this->renameMap = [];
        foreach ($configuration as $old => $new) {
            if (is_string($old) && is_string($new)) {
                $this->renameMap[$old] = $new;
            }
        }
    }

    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return null;
        }

        $oldName = $node->name->toString();
        if (!isset($this->renameMap[$oldName])) {
            return null;
        }

        $node->name = new Identifier($this->renameMap[$oldName]);

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Renames method calls by name only, ignoring the receiver type. For legacy XOOPS code where receivers have no type hints.',
            [new ConfiguredCodeSample(
                <<<'CODE'
$xoopsDB->queryF("UPDATE x SET y=1");
$db->quoteString($value);
CODE,
                <<<'CODE'
$xoopsDB->exec("UPDATE x SET y=1");
$db->quote($value);
CODE,
                ['queryF' => 'exec', 'quoteString' => 'quote']
            )]
        );
    }
}
