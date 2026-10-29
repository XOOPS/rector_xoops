<?php

namespace App;

$db = Database::getInstance();
$core = \Database::getInstance();
$ok = xoops_refcheck();

?>
-----
<?php

namespace App;

$db = Database::getInstance();
$core = \XoopsDatabaseFactory::getDatabaseConnection();
$ok = $GLOBALS['xoopsSecurity']->checkReferer();

?>
