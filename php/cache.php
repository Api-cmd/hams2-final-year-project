<?php

function cache_get(string $key): mixed {
    $file = __DIR__ . '/../cache/' . md5($key) . '.cache';

    if (!file_exists($file)) return null;

    $data = unserialize(file_get_contents($file));

    // Check if cache has expired
    if ($data['expires'] < time()) {
        unlink($file); // Delete expired cache file
        return null;
    }

    return $data['value'];
}

function cache_set(string $key, mixed $value, int $seconds = 300): void {
    $dir = __DIR__ . '/../cache/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $file = $dir . md5($key) . '.cache';
    file_put_contents($file, serialize([
        'value'   => $value,
        'expires' => time() + $seconds,
    ]));
}

function cache_clear(string $key): void {
    $file = __DIR__ . '/../cache/' . md5($key) . '.cache';
    if (file_exists($file)) unlink($file);
}