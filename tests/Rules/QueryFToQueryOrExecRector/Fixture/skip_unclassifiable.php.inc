<?php

// Each of these stays queryF(): the statement keyword cannot be read here.
function run($db, $sql)
{
    return $db->queryF($sql);
}

$mixed = 'SELECT 1';
$mixed = 'DELETE FROM x';
$xoopsDB->queryF($mixed);

$built = $prefix . ' FROM x';
$xoopsDB->queryF($built);

$xoopsDB->queryF($table . ' x');

$outer = 'DELETE FROM x';
function other($db)
{
    return $db->queryF($outer);
}

// The incoming $sql may be a DELETE; the later SELECT does not reach the call.
function later($db, $sql)
{
    $db->queryF($sql);
    $sql = 'SELECT 1';
}

// The arrow function's $sql is its own parameter, not the outer SELECT.
$sql = 'SELECT 1';
$cb = fn ($sql) => $xoopsDB->queryF($sql);

// A literal followed by more SQL can stop inside the keyword.
$xoopsDB->queryF('S' . 'ELECT 1');
$xoopsDB->queryF(sprintf('S%sLECT 1', 'E'));

foreach ($statements as $stmt) {
    $xoopsDB->queryF($stmt);
}

// A reference alias or a by-reference argument can rewrite the SQL.
function aliased($db)
{
    $sql = 'SELECT 1';
    $alias =& $sql;
    $alias = 'DELETE FROM x';
    $db->queryF($sql);
}

function byRef($db)
{
    $sql = 'SELECT 1';
    rewrite_sql($sql);
    $db->queryF($sql);
}

function compound($db)
{
    $sql = 'SELECT * FROM t';
    $sql ^= 'SELECT * FROM t' ^ 'DELETE   FROM t';
    $db->queryF($sql);
}

?>
