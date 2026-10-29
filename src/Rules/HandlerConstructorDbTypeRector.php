<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Adds the `\XoopsDatabase` type to an untyped `$db` constructor parameter, but
 * ONLY on classes that extend a XOOPS handler base — that is where the parent
 * constructor declares `XoopsDatabase`, so the hint is correct and compatible.
 * Non-handler classes are left alone.
 *
 * @license GPL-2.0-or-later
 */
final class HandlerConstructorDbTypeRector extends AbstractRector
{
    private const HANDLER_BASES = [
        'xoopspersistableobjecthandler',
        'xoopsobjecthandler',
    ];

    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof Class_ || !$node->extends instanceof Name) {
            return null;
        }

        if (!in_array($node->extends->toLowerString(), self::HANDLER_BASES, true)) {
            return null;
        }

        $constructor = $node->getMethod('__construct');
        if ($constructor === null) {
            return null;
        }

        $changed = false;
        foreach ($constructor->params as $i => $param) {
            if ($param->type === null
                && $param->var instanceof Node\Expr\Variable
                && $param->var->name === 'db') {
                // `$db = null` needs `?`: an implicitly nullable type is deprecated in PHP 8.4.
                $type = new FullyQualified('XoopsDatabase');
                $nullDefault = $param->default instanceof ConstFetch
                    && $param->default->name->toLowerString() === 'null';
                $param->type = $nullDefault ? new NullableType($type) : $type;
                // Before a required parameter the default is ignored and, once typed `?T`,
                // deprecated (optional-before-required): keep it nullable but required.
                if ($nullDefault && $this->hasRequiredAfter($constructor->params, $i)) {
                    $param->default = null;
                }
                $changed = true;
            }
        }

        return $changed ? $node : null;
    }

    /** @param Node\Param[] $params */
    private function hasRequiredAfter(array $params, int $index): bool
    {
        foreach (array_slice($params, $index + 1) as $param) {
            if ($param->default === null && !$param->variadic) {
                return true;
            }
        }

        return false;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Types an untyped $db constructor parameter as \XoopsDatabase on XOOPS handler subclasses.',
            [new CodeSample(
                <<<'CODE'
class MyHandler extends XoopsPersistableObjectHandler
{
    public function __construct($db) { parent::__construct($db, 'x', 'X'); }
}
CODE,
                <<<'CODE'
class MyHandler extends XoopsPersistableObjectHandler
{
    public function __construct(\XoopsDatabase $db) { parent::__construct($db, 'x', 'X'); }
}
CODE
            )]
        );
    }
}
