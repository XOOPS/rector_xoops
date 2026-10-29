<?php

namespace Demo;

final class PathResolver
{
    public function path(string $p): string
    {
        return __DIR__ . '/' . $p;
    }
}

function load(): void
{
    $xoops = new PathResolver();
    include_once $xoops->path('class/xoopsuser.php');
}

function loadTyped(PathResolver $xoops): void
{
    include_once $xoops->path('class/xoopsobject.php');
}

?>
