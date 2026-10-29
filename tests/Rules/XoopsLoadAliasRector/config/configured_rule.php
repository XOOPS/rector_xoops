<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Xoops\Rector\Rules\XoopsLoadAliasRector;

return RectorConfig::configure()->withRules([XoopsLoadAliasRector::class]);
