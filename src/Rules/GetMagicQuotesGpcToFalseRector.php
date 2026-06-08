<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * `get_magic_quotes_gpc()` → `false`.
 *
 * The function was removed in PHP 8 and always returned `false` from PHP 5.4 on.
 * Replacing the call with `false` lets Rector's dead-code sets drop the now-dead
 * `if (false) { … }` branches. No `@` suppression is emitted (XOOPS forbids it).
 *
 * @license GPL-2.0-or-later
 */
final class GetMagicQuotesGpcToFalseRector extends AbstractRector
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

        if ($node->name->toLowerString() !== 'get_magic_quotes_gpc') {
            return null;
        }

        return new ConstFetch(new Name('false'));
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replaces the removed get_magic_quotes_gpc() with false.',
            [new CodeSample(
                'if (get_magic_quotes_gpc()) {}',
                'if (false) {}'
            )]
        );
    }
}
