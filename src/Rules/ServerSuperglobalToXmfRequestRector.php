<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces raw reads of whitelisted `$_SERVER[...]` keys with filtered
 * `\Xmf\Request::getString('KEY', '', 'SERVER')`.
 *
 * Behaviour-changing (raw → filtered), so it ships in the opt-in `XOOPS_RISKY`
 * set, not the default set. Only the configured keys are rewritten; everything
 * else is untouched. Writes to `$_SERVER[...]` are effectively non-existent in
 * real code, so assignment-target detection is intentionally omitted.
 *
 * @license GPL-2.0-or-later
 */
final class ServerSuperglobalToXmfRequestRector extends AbstractRector implements ConfigurableRectorInterface
{
    /** @var list<string> */
    private array $keys = [];

    /** @param mixed $configuration */
    public function configure($configuration): void
    {
        $this->keys = is_array($configuration)
            ? array_values(array_filter($configuration, 'is_string'))
            : [];
    }

    public function getNodeTypes(): array
    {
        return [ArrayDimFetch::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof ArrayDimFetch) {
            return null;
        }

        if (!$node->var instanceof Variable || $node->var->name !== '_SERVER') {
            return null;
        }

        if (!$node->dim instanceof String_ || !in_array($node->dim->value, $this->keys, true)) {
            return null;
        }

        return new StaticCall(
            new FullyQualified('Xmf\Request'),
            new Identifier('getString'),
            [new Arg(new String_($node->dim->value)), new Arg(new String_('')), new Arg(new String_('SERVER'))]
        );
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Replaces raw \$_SERVER['KEY'] reads with \\Xmf\\Request::getString('KEY', '', 'SERVER') for whitelisted keys.",
            [new ConfiguredCodeSample(
                "\$ref = \$_SERVER['HTTP_REFERER'];",
                "\$ref = \\Xmf\\Request::getString('HTTP_REFERER', '', 'SERVER');",
                ['HTTP_REFERER']
            )]
        );
    }
}
