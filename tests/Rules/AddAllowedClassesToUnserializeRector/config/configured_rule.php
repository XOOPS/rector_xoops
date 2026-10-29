<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Xoops\Rector\Rules\AddAllowedClassesToUnserializeRector;

return RectorConfig::configure()->withRules([AddAllowedClassesToUnserializeRector::class]);
