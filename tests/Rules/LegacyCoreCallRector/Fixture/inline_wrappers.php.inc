<?php

if (!xoops_refcheck()) {
    exit;
}
$ok = xoops_refcheck(0);
$db = Database::getInstance();
$other = Foo::getInstance();
$security->checkReferer();

?>
-----
<?php

if (!$GLOBALS['xoopsSecurity']->checkReferer()) {
    exit;
}
$ok = $GLOBALS['xoopsSecurity']->checkReferer(0);
$db = \XoopsDatabaseFactory::getDatabaseConnection();
$other = Foo::getInstance();
$security->checkReferer();

?>
