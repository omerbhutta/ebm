<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Simple file-based cache.
 */
final class Cache
{
    public static function get(string $key, $default = null)
    {
        $file = self::file($key);
        if (!is_file($file)) return $default;
        $raw = (string)file_get_contents($file);
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['expires'], $data['value'])) return $default;
        if ($data['expires'] > 0 && $data['expires'] < time()) {
            @unlink($file);
            return $default;
        }
        return $data['value'];
    }

    public static function put(string $key, $value, int $ttlSeconds = 0): bool
    {
        $file = self::file($key);
        $payload = [
            'expires' => $ttlSeconds > 0 ? time() + $ttlSeconds : 0,
            'value'   => $value,
        ];
        return @file_put_contents($file, json_encode($payload), LOCK_EX) !== false;
    }

    public static function forget(string $key): void
    {
        $file = self::file($key);
        if (is_file($file)) @unlink($file);
    }

    public static function forgetPattern(string $pattern): int
    {
        $dir = self::dir();
        $files = glob($dir . DIRECTORY_SEPARATOR . $pattern) ?: [];
        $n = 0;
        foreach ($files as $f) {
            if (@unlink($f)) $n++;
        }
        return $n;
    }

    public static function flush(): int
    {
        $dir = self::dir();
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.cache') ?: [];
        $n = 0;
        foreach ($files as $f) {
            if (@unlink($f)) $n++;
        }
        return $n;
    }

    public static function age(string $key): ?int
    {
        $file = self::file($key);
        if (!is_file($file)) return null;
        return time() - filemtime($file);
    }

    private static function file(string $key): string
    {
        return self::dir() . DIRECTORY_SEPARATOR . self::sanitize($key) . '.cache';
    }

    private static function dir(): string
    {
        $dir = App::instance()->storagePath('cache');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $ht = $dir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
        return $dir;
    }

    private static function sanitize(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
    }
}
