<?php

$result = $xoopsDB->queryF("SELECT * FROM x");
$xoopsDB->queryF('DELETE FROM ' . $xoopsDB->prefix('x') . ' WHERE id = ' . $id);
$xoopsDB->queryF("UPDATE {$table} SET hits = hits + 1");
$xoopsDB->queryF(sprintf('INSERT INTO %s (a) VALUES (%u)', $xoopsDB->prefix('x'), $a));
$rows = $xoopsDB->queryF('  show tables');
$xoopsDB->queryF('SET NAMES utf8mb4');

?>
-----
<?php

$result = $xoopsDB->query("SELECT * FROM x");
$xoopsDB->exec('DELETE FROM ' . $xoopsDB->prefix('x') . ' WHERE id = ' . $id);
$xoopsDB->exec("UPDATE {$table} SET hits = hits + 1");
$xoopsDB->exec(sprintf('INSERT INTO %s (a) VALUES (%u)', $xoopsDB->prefix('x'), $a));
$rows = $xoopsDB->query('  show tables');
$xoopsDB->exec('SET NAMES utf8mb4');

?>
