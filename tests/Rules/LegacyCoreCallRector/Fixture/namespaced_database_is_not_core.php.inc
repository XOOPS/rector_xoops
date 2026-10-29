<?php

namespace App;

$db = Database::getInstance();
$core = \Database::getInstance();
$ok = xoops_refcheck();
$dynamic = $Database::getInstance();
$method = \Database::$getInstance();

?>
-----
<?php

namespace App;

$db = Database::getInstance();
$core = \XoopsDatabaseFactory::getDatabaseConnection();
$ok = $GLOBALS['xoopsSecurity']->checkReferer();
$dynamic = $Database::getInstance();
$method = \Database::$getInstance();

?>
