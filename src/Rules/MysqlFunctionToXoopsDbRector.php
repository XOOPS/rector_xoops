<?php

declare(strict_types=1);

namespace Xoops\Rector\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Xoops\Rector\Support\SqlKeywordDetector;

/**
 * Maps removed `mysql_*` / `mysqli_*` procedural functions to method calls on the
 * XOOPS DB wrapper `$GLOBALS['xoopsDB']`.
 *
 * Smarter than a text substitution:
 *  - For `mysqli_*` functions whose first argument is the connection (`$link`), the
 *    first arg is DROPPED — `$GLOBALS['xoopsDB']` already wraps that connection.
 *  - For `mysql_query` / `mysqli_query` the target method is chosen from the SQL:
 *    SELECT/SHOW/… → `query()` (returns a fetchable result), INSERT/UPDATE/DELETE/DDL
 *    → `exec()` (returns bool). This matches the XOOPS 2.7 query/exec split that
 *    replaces the deprecated `queryF()`. When the SQL is not a literal string, it
 *    falls back to `query()` (fetchable, non-breaking) — review those by hand.
 *
 * @license GPL-2.0-or-later
 */
final class MysqlFunctionToXoopsDbRector extends AbstractRector
{
    use SqlKeywordDetector;

    /**
     * @var array<string, array{method: string, dropFirstArg: bool}>
     */
    private const MAP = [
        // mysql_*  — pre-PHP 7. None of these take a connection as arg #1
        // (mysql_* used the implicit "last" connection), so keep all args.
        // NOTE: mysql_query / mysqli_query are handled separately (query vs exec
        // routing) and are intentionally NOT in this map.
        'mysql_real_escape_string' => ['method' => 'escape',          'dropFirstArg' => false],
        'mysql_escape_string' => ['method' => 'escape',          'dropFirstArg' => false],
        'mysql_num_rows' => ['method' => 'getRowsNum',      'dropFirstArg' => false],
        'mysql_num_fields' => ['method' => 'getFieldsNum',    'dropFirstArg' => false],
        'mysql_fetch_assoc' => ['method' => 'fetchArray',      'dropFirstArg' => false],
        'mysql_fetch_array' => ['method' => 'fetchBoth',       'dropFirstArg' => false],
        'mysql_fetch_row' => ['method' => 'fetchRow',        'dropFirstArg' => false],
        'mysql_fetch_object' => ['method' => 'fetchObject',     'dropFirstArg' => false],
        'mysql_free_result' => ['method' => 'freeRecordSet',   'dropFirstArg' => false],
        'mysql_insert_id' => ['method' => 'getInsertId',     'dropFirstArg' => false],
        'mysql_affected_rows' => ['method' => 'getAffectedRows', 'dropFirstArg' => false],
        'mysql_error' => ['method' => 'error',           'dropFirstArg' => false],
        'mysql_errno' => ['method' => 'errno',           'dropFirstArg' => false],
        'mysql_field_name' => ['method' => 'getFieldName',    'dropFirstArg' => false],
        'mysql_get_server_info' => ['method' => 'getServerVersion','dropFirstArg' => false],
        'mysql_close' => ['method' => 'close',           'dropFirstArg' => false],

        // mysqli_*  — these DO take the connection as arg #1 (or #2 for ones
        // that operate on a result, where arg #1 is the result). Drop arg #1
        // only for the connection-first ones. Result-first ones keep all args.
        'mysqli_real_escape_string' => ['method' => 'escape',           'dropFirstArg' => true],
        'mysqli_insert_id' => ['method' => 'getInsertId',      'dropFirstArg' => true],
        'mysqli_affected_rows' => ['method' => 'getAffectedRows',  'dropFirstArg' => true],
        'mysqli_error' => ['method' => 'error',            'dropFirstArg' => true],
        'mysqli_errno' => ['method' => 'errno',            'dropFirstArg' => true],
        'mysqli_close' => ['method' => 'close',            'dropFirstArg' => true],
        // Result-first: arg #1 IS the result/handle, keep it.
        'mysqli_num_rows' => ['method' => 'getRowsNum',       'dropFirstArg' => false],
        'mysqli_fetch_row' => ['method' => 'fetchRow',         'dropFirstArg' => false],
        'mysqli_fetch_assoc' => ['method' => 'fetchArray',       'dropFirstArg' => false],
        'mysqli_fetch_array' => ['method' => 'fetchBoth',        'dropFirstArg' => false],
        'mysqli_fetch_object' => ['method' => 'fetchObject',      'dropFirstArg' => false],
        'mysqli_free_result' => ['method' => 'freeRecordSet',    'dropFirstArg' => false],
        'mysqli_num_fields' => ['method' => 'getFieldsNum',     'dropFirstArg' => false],
    ];

    public function getNodeTypes(): array
    {
        return [FuncCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name) {
            return null;
        }

        $name = $node->name->toLowerString();

        // mysql_query / mysqli_query → query() for reads, exec() for writes.
        if ('mysql_query' === $name || 'mysqli_query' === $name) {
            $args = $node->args;
            // mysqli_query takes the connection as arg #1; drop it.
            if ('mysqli_query' === $name && count($args) > 0) {
                array_shift($args);
            }
            $method = $this->isWriteSql($args[0] ?? null) ? 'exec' : 'query';

            return new MethodCall($this->xoopsDb(), new Identifier($method), $args);
        }

        if (!isset(self::MAP[$name])) {
            return null;
        }

        $config = self::MAP[$name];
        $args = $node->args;

        if ($config['dropFirstArg'] && count($args) > 0) {
            array_shift($args);
        }

        return new MethodCall($this->xoopsDb(), new Identifier($config['method']), $args);
    }

    /** Build the `$GLOBALS['xoopsDB']` receiver. */
    private function xoopsDb(): ArrayDimFetch
    {
        return new ArrayDimFetch(new Variable('GLOBALS'), new String_('xoopsDB'));
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replaces removed mysql_*/mysqli_* procedural calls with calls on the XOOPS DB wrapper.',
            [new CodeSample(
                <<<'CODE'
$result = mysql_query("SELECT * FROM x");
$row    = mysql_fetch_assoc($result);
mysql_query("UPDATE x SET y = 1");
$id     = mysqli_insert_id($link);
CODE,
                <<<'CODE'
$result = $GLOBALS['xoopsDB']->query("SELECT * FROM x");
$row    = $GLOBALS['xoopsDB']->fetchArray($result);
$GLOBALS['xoopsDB']->exec("UPDATE x SET y = 1");
$id     = $GLOBALS['xoopsDB']->getInsertId();
CODE
            )]
        );
    }
}
