<?php

spl_autoload_register(function ($class): void {
    if (str_starts_with($class, 'acroforms\\')) {
        $filename = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 9)) . '.php';
        $fullpath = __DIR__ . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($fullpath)) {
            require_once $fullpath;
        }
    }
});
