<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Xoops\Rector\Rules\LegacyCoreCallRector;

return RectorConfig::configure()->withRules([LegacyCoreCallRector::class]);
