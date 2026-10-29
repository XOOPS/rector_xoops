<?php

$ids = unserialize($config['ids']);
if (!is_array($ids)) {
    $ids = [];
}

$opts = unserialize($raw);
$valid = $opts !== false && is_array($opts);

?>
-----
<?php

$ids = unserialize($config['ids'], ['allowed_classes' => false]);
if (!is_array($ids)) {
    $ids = [];
}

$opts = unserialize($raw, ['allowed_classes' => false]);
$valid = $opts !== false && is_array($opts);

?>
