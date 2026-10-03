<?php

/**
 * Re-applies the beyondcode/laravel-websockets 1.14 TriggerEventController fix
 * after composer install (vendor edits are otherwise wiped).
 */
$root = dirname(__DIR__);
$lockPath = $root.'/composer.lock';
$source = __DIR__.'/TriggerEventController.php';
$target = $root.'/vendor/beyondcode/laravel-websockets/src/HttpApi/Controllers/TriggerEventController.php';

if (! is_file($lockPath) || ! is_file($source)) {
    fwrite(STDERR, "websockets trigger patch: missing composer.lock or source, skip\n");
    exit(0);
}

$lock = json_decode((string) file_get_contents($lockPath), true);
$version = null;
foreach ($lock['packages'] ?? [] as $package) {
    if (($package['name'] ?? '') === 'beyondcode/laravel-websockets') {
        $version = $package['version'] ?? null;
        break;
    }
}

if ($version !== '1.14.0') {
    fwrite(STDERR, "websockets trigger patch: expected beyondcode/laravel-websockets 1.14.0, found ".($version ?? 'none')." — skip\n");
    exit(0);
}

if (! is_file($target)) {
    fwrite(STDERR, "websockets trigger patch: package is not installed, skip\n");
    exit(0);
}

$expected = (string) file_get_contents($source);
$current = (string) file_get_contents($target);
if ($current === $expected) {
    exit(0);
}

if (! copy($source, $target)) {
    fwrite(STDERR, "websockets trigger patch: failed to write {$target}\n");
    exit(1);
}

fwrite(STDOUT, "Patched beyondcode/laravel-websockets TriggerEventController (channels array)\n");
