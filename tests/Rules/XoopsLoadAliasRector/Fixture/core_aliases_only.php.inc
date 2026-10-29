<?php

xoops_load('cache');
xoops_load("Uploader");
xoops_load('captcha', 'core');
xoops_load('model', 'class');
xoops_load('utility', 'mymodule');
xoops_load('cache', 'CORE');
xoops_load('xoopscache');
xoops_load($name);
xoops_load(type: 'cache', name: 'core');
xoops_load(...['cache']);

?>
-----
<?php

xoops_load('xoopscache');
xoops_load("xoopsmediauploader");
xoops_load('xoopscaptcha', 'core');
xoops_load('xoopsmodelfactory', 'class');
xoops_load('utility', 'mymodule');
xoops_load('cache', 'CORE');
xoops_load('xoopscache');
xoops_load($name);
xoops_load(type: 'cache', name: 'core');
xoops_load(...['cache']);

?>
