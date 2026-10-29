<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Xoops\Rector\Rules\QueryFToQueryOrExecRector;

return RectorConfig::configure()->withRules([QueryFToQueryOrExecRector::class]);
