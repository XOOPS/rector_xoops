<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Xoops\Rector\Support\SqlKeywordDetector;

/**
 * Replaces the deprecated `$db->queryF($sql, ...)` with the XOOPS 2.7 split:
 * `query()` for reads (SELECT/SHOW/…, returns a fetchable result) and `exec()`
 * for writes/DDL (INSERT/UPDATE/DELETE/…, returns bool).
 *
 * Name-only matching (any receiver), because legacy XOOPS receivers like
 * `$xoopsDB` / `$this->db` usually have no type hint for PHPStan to resolve.
 * A non-literal SQL argument falls back to `query()` (fetchable, non-breaking) —
 * review those by hand. All arguments are preserved.
 *
 * @license GPL-2.0-or-later
 */
final class QueryFToQueryOrExecRector extends AbstractRector
{
    use SqlKeywordDetector;

    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return null;
        }

        if ($node->name->toLowerString() !== 'queryf') {
            return null;
        }

        $method = $this->isWriteSql($node->args[0] ?? null) ? 'exec' : 'query';
        $node->name = new Identifier($method);

        return $node;
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replaces deprecated queryF() with query() (reads) or exec() (writes) per the XOOPS 2.7 split, chosen from the SQL keyword.',
            [new CodeSample(
                <<<'CODE'
$result = $xoopsDB->queryF("SELECT * FROM x");
$xoopsDB->queryF("DELETE FROM x WHERE id = 1");
CODE,
                <<<'CODE'
$result = $xoopsDB->query("SELECT * FROM x");
$xoopsDB->exec("DELETE FROM x WHERE id = 1");
CODE
            )]
        );
    }
}
