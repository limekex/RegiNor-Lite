<?php

declare(strict_types=1);

require_once __DIR__ . '/i18n.php';

// Runtime classes stay self-contained: the deployable plugin needs no Composer vendor directory.
spl_autoload_register(static function (string $class): void {
    $prefix = 'RegiNor\\Lite\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $relative)) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
