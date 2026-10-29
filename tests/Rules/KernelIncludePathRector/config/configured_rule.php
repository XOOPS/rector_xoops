<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Xoops\Rector\Rules\KernelIncludePathRector;

return RectorConfig::configure()->withRules([KernelIncludePathRector::class]);
