<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Renames the PHP 4 long-form superglobals to their PHP 5+ short forms.
 * Removed in PHP 5.4, but legacy XOOPS modules still reference them.
 *
 * @license GPL-2.0-or-later
 */
final class RenameLegacyHttpVarsRector extends AbstractRector
{
    /** @var array<string, string> */
    private const MAP = [
        'HTTP_POST_VARS' => '_POST',
        'HTTP_GET_VARS' => '_GET',
        'HTTP_SERVER_VARS' => '_SERVER',
        'HTTP_COOKIE_VARS' => '_COOKIE',
        'HTTP_ENV_VARS' => '_ENV',
        'HTTP_SESSION_VARS' => '_SESSION',
        'HTTP_POST_FILES' => '_FILES',
    ];

    public function getNodeTypes(): array
    {
        return [Variable::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Variable || !is_string($node->name)) {
            return null;
        }

        if (!isset(self::MAP[$node->name])) {
            return null;
        }

        $node->name = self::MAP[$node->name];

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Renames PHP 4 long-form superglobals ($HTTP_POST_VARS etc.) to the PHP 5+ short forms.',
            [new CodeSample(
                '$id = $HTTP_POST_VARS["id"];',
                '$id = $_POST["id"];'
            )]
        );
    }
}
