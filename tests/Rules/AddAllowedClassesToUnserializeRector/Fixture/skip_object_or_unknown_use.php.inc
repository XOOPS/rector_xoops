<?php

// A cached CriteriaCompo: allowed_classes => false would hand back __PHP_Incomplete_Class.
$criteria = unserialize($cache);
$items = $handler->getObjects($criteria);

$obj = unserialize($stored);
echo $obj->getVar('title');

function load($blob)
{
    return unserialize($blob);
}

$list = unserialize($raw);
$filter = function () use ($list) {
    return $list;
};
foreach ($list as $entry) {
}

$data = unserialize($payload);

// Arrays can hold objects: any use that reads an element keeps them real objects.
foreach (unserialize($rows) as $row) {
    echo $row->getVar('id');
}
$cfg = unserialize($raw2);
echo $cfg['title'];
echo json_encode(unserialize($raw3));
$first = current(unserialize($raw4));

// count(), key checks and array_keys() also take a serialized ArrayObject or compare elements.
$n = count(unserialize($raw5));
$set = unserialize($raw6);
if (isset($set['k'])) {
}
$keys = array_keys(unserialize($raw7), $needle);
$named = is_array(unserialize(data: $raw8));

// The value escapes through a chained assignment or a reference alias.
function chained($p)
{
    $copy = $x = unserialize($p);
    is_array($x);

    return $copy;
}

function aliased($p)
{
    $a = &$x;
    $x = unserialize($p);
    is_array($x);

    return $a;
}

$opts = unserialize($raw, ['allowed_classes' => ['XoopsObject']]);

?>
