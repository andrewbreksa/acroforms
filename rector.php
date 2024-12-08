<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withDeadCodeLevel(50)
    ->withPhpPolyfill()
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withPhpSets(php84: true);
