<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Inlines two removed core wrappers the way their 2.5 bodies forwarded them:
 * `xoops_refcheck($d)` → `$GLOBALS['xoopsSecurity']->checkReferer($d)` (an instance
 * method, so a static `XoopsSecurity::checkReferer()` would be a fatal error) and
 * `Database::getInstance()` → `\XoopsDatabaseFactory::getDatabaseConnection()`.
 *
 * @license GPL-2.0-or-later
 */
final class LegacyCoreCallRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [FuncCall::class, StaticCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof FuncCall && $this->isName($node, 'xoops_refcheck')) {
            return new MethodCall(
                new ArrayDimFetch(new Variable('GLOBALS'), new String_('xoopsSecurity')),
                'checkReferer',
                $node->args
            );
        }

        if ($node instanceof StaticCall
            && $this->isName($node->class, 'Database')
            && $this->isName($node->name, 'getInstance')
            && $node->args === []
        ) {
            return new StaticCall(new FullyQualified('XoopsDatabaseFactory'), 'getDatabaseConnection');
        }

        return null;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Inlines the removed xoops_refcheck() and Database::getInstance() wrappers.',
            [new CodeSample(
                "xoops_refcheck();\n\$db = Database::getInstance();",
                "\$GLOBALS['xoopsSecurity']->checkReferer();\n\$db = \\XoopsDatabaseFactory::getDatabaseConnection();"
            )]
        );
    }
}
