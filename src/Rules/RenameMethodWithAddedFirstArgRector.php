<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Renames a method call AND prepends a string-literal first argument — for APIs
 * that collapsed several methods into one dispatcher, e.g. Smarty 2→4:
 *
 *   $tpl->register_function('x', $cb)  →  $tpl->registerPlugin('function', 'x', $cb)
 *   $tpl->register_prefilter($cb)      →  $tpl->registerFilter('pre', $cb)
 *
 * Name-only matching (any receiver), so it works on untyped `$xoopsTpl` / `$smarty`.
 *
 * Configuration: oldMethod => [newMethod, firstArgLiteral]
 *
 * @license GPL-2.0-or-later
 */
final class RenameMethodWithAddedFirstArgRector extends AbstractRector implements ConfigurableRectorInterface
{
    /** @var array<string, array{0: string, 1: string}> */
    private array $map = [];

    /** @param mixed $configuration */
    public function configure($configuration): void
    {
        if (!is_array($configuration)) {
            return;
        }
        $this->map = [];
        foreach ($configuration as $old => $spec) {
            if (is_string($old) && is_array($spec) && isset($spec[0], $spec[1])
                && is_string($spec[0]) && is_string($spec[1])) {
                $this->map[$old] = [$spec[0], $spec[1]];
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

        $old = $node->name->toString();
        if (!isset($this->map[$old])) {
            return null;
        }

        [$newMethod, $firstArg] = $this->map[$old];
        $node->name = new Identifier($newMethod);
        array_unshift($node->args, new Arg(new String_($firstArg)));

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Renames a method call and prepends a string-literal first argument (e.g. Smarty register_* → registerPlugin/registerFilter).',
            [new ConfiguredCodeSample(
                <<<'CODE'
$tpl->register_function('truncate', $cb);
$tpl->register_prefilter($cb);
CODE,
                <<<'CODE'
$tpl->registerPlugin('function', 'truncate', $cb);
$tpl->registerFilter('pre', $cb);
CODE,
                ['register_function' => ['registerPlugin', 'function'], 'register_prefilter' => ['registerFilter', 'pre']]
            )]
        );
    }
}
