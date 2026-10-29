<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Xoops\Rector\Rules\GetLinkedUnameFromIdRector;

return RectorConfig::configure()->withRules([GetLinkedUnameFromIdRector::class]);
