<?php

class Loader
{
    public function load($raw)
    {
        $data = unserialize($raw);
        return is_array($data);
    }
}

function direct($raw)
{
    return is_array(unserialize($raw));
}

$closure = function ($raw) {
    $data = unserialize($raw) ?? [];
    return is_array($data);
};

$fallback = function ($raw) {
    $data = unserialize($raw) ?: [];
    return is_array($data);
};

foreach ($blobs as $blob) {
    $row = unserialize($blob);
    if (is_array($row)) {
        $rows[] = $blob;
    }
}

?>
-----
<?php

class Loader
{
    public function load($raw)
    {
        $data = unserialize($raw, ['allowed_classes' => false]);
        return is_array($data);
    }
}

function direct($raw)
{
    return is_array(unserialize($raw, ['allowed_classes' => false]));
}

$closure = function ($raw) {
    $data = unserialize($raw, ['allowed_classes' => false]) ?? [];
    return is_array($data);
};

$fallback = function ($raw) {
    $data = unserialize($raw, ['allowed_classes' => false]) ?: [];
    return is_array($data);
};

foreach ($blobs as $blob) {
    $row = unserialize($blob, ['allowed_classes' => false]);
    if (is_array($row)) {
        $rows[] = $blob;
    }
}

?>
