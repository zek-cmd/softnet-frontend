<?php

if (!defined('ASSET_ROOT')) {
    define('ASSET_ROOT', dirname(__DIR__));
}

function asset(string $path): string
{
    $path = '/' . ltrim($path, '/');

    if (strpos($path, '..') !== false) {
        return $path;
    }

    $root = realpath(ASSET_ROOT);
    $real = realpath(ASSET_ROOT . $path);

    if ($root === false || $real === false || strpos($real, $root) !== 0) {
        return $path;
    }

    $mtime = @filemtime($real);

    if ($mtime === false) {
        return $path;
    }

    return $path . '?v=' . $mtime;
}
