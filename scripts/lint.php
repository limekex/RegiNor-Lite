<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failed = false;
$count = 0;

foreach (['plugin', 'scripts', 'tests'] as $directory) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory));

    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        passthru(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()), $status);
        $failed = $failed || $status !== 0;
        ++$count;
    }
}

echo sprintf("Kontrollerte %d PHP-filer.\n", $count);
exit($failed ? 1 : 0);
