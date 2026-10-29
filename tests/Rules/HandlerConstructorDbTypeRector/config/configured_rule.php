<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Xoops\Rector\Rules\HandlerConstructorDbTypeRector;

return RectorConfig::configure()->withRules([HandlerConstructorDbTypeRector::class]);
