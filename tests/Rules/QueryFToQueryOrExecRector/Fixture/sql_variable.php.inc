<?php

$sql = 'DELETE FROM ' . $xoopsDB->prefix('x');
if ($id > 0) {
    $sql = sprintf('DELETE FROM %s WHERE id = %u', $xoopsDB->prefix('x'), $id);
}
$xoopsDB->queryF($sql);

function readRows($db)
{
    $query = "SELECT id FROM " . $db->prefix('x');
    return $db->queryF($query);
}

?>
-----
<?php

$sql = 'DELETE FROM ' . $xoopsDB->prefix('x');
if ($id > 0) {
    $sql = sprintf('DELETE FROM %s WHERE id = %u', $xoopsDB->prefix('x'), $id);
}
$xoopsDB->exec($sql);

function readRows($db)
{
    $query = "SELECT id FROM " . $db->prefix('x');
    return $db->query($query);
}

?>
