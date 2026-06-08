<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces the legacy `new ModuleAdmin()` with the XMF singleton
 * `\Xmf\Module\Admin::getInstance()`. Xmf\Module\Admin is the modern drop-in for
 * the old global ModuleAdmin class and exposes the same render and display methods.
 *
 * @license GPL-2.0-or-later
 */
final class NewModuleAdminToXmfRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [New_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof New_ || !$node->class instanceof Name) {
            return null;
        }

        if (strtolower($node->class->toString()) !== 'moduleadmin') {
            return null;
        }

        return new StaticCall(
            new FullyQualified('Xmf\Module\Admin'),
            new Identifier('getInstance')
        );
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replaces new ModuleAdmin() with \Xmf\Module\Admin::getInstance().',
            [new CodeSample(
                '$moduleAdmin = new ModuleAdmin();',
                '$moduleAdmin = \Xmf\Module\Admin::getInstance();'
            )]
        );
    }
}
