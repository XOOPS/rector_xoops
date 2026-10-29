<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Rewrites the deprecated short names of `xoops_load()` to the class names the core
 * maps them to (`XoopsLoad::load()`'s `$deprecated` table, 2.7.4), which also stops
 * the deprecation log entry on every load: `xoops_load('cache')` → `xoops_load('xoopscache')`.
 *
 * The core maps them only for the `core` / `class` types, so a call with any other
 * second argument (`xoops_load('utility', 'mymodule')` loads a module's own class) is
 * left alone.
 *
 * @license GPL-2.0-or-later
 */
final class XoopsLoadAliasRector extends AbstractRector
{
    private const ALIASES = [
        'uploader' => 'xoopsmediauploader',
        'utility' => 'xoopsutility',
        'captcha' => 'xoopscaptcha',
        'cache' => 'xoopscache',
        'file' => 'xoopsfile',
        'model' => 'xoopsmodelfactory',
        'calendar' => 'xoopscalendar',
        'userutility' => 'xoopsuserutility',
    ];

    public function getNodeTypes(): array
    {
        return [FuncCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof FuncCall
            || !$node->name instanceof Name
            || !$this->isName($node, 'xoops_load')
        ) {
            return null;
        }
        // Positions decide which argument is the name: skip named or unpacked arguments.
        foreach ($node->args as $arg) {
            if (!$arg instanceof Arg || $arg->name !== null || $arg->unpack) {
                return null;
            }
        }
        $nameArg = $node->args[0] ?? null;
        if (!$nameArg instanceof Arg || !$nameArg->value instanceof String_) {
            return null;
        }

        $type = $node->args[1] ?? null;
        if ($type instanceof Arg && !($type->value instanceof String_
            && in_array(strtolower($type->value->value), ['core', 'class'], true))
        ) {
            return null;
        }

        $name = $nameArg->value;
        $target = self::ALIASES[strtolower($name->value)] ?? null;
        if ($target === null) {
            return null;
        }
        $nameArg->value = new String_($target, ['kind' => $name->getAttribute('kind', String_::KIND_SINGLE_QUOTED)]);

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Rewrites deprecated xoops_load() short names ('cache', 'uploader', …) to the core class names.",
            [new CodeSample(
                "xoops_load('cache');\nxoops_load('utility', 'mymodule');",
                "xoops_load('xoopscache');\nxoops_load('utility', 'mymodule');"
            )]
        );
    }
}
