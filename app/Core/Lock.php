<?php
declare(strict_types=1);

namespace App\Core;

/**
 * File-based advisory lock using flock().
 * Storage path: storage/locks/<name>.lock
 *
 * Usage:
 *   $lock = Lock::acquire('cron_refresh', 0);     // try non-blocking
 *   if (!$lock) { ...already running... }
 *   try { ...do work... } finally { Lock::release($lock); }
 */
final class Lock
{
    private const DIR = 'locks';

    public static function path(string $name): string
    {
        $safe = preg_replace('/[^a-z0-9_\-]/i', '_', $name);
        $dir = \App\Core\App::instance()->storagePath(self::DIR);
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return $dir . DIRECTORY_SEPARATOR . $safe . '.lock';
    }

    /**
     * Acquire a lock. Returns an array with the file handle on success, or null
     * if the lock could not be obtained within $waitMs.
     *
     * @return array{handle:resource,path:string}|null
     */
    public static function acquire(string $name, int $waitMs = 0)
    {
        $path = self::path($name);
        $fp = @fopen($path, 'c+');
        if (!$fp) return null;
        $start = microtime(true);
        do {
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                ftruncate($fp, 0);
                fwrite($fp, (string)getmypid());
                fflush($fp);
                return ['handle' => $fp, 'path' => $path];
            }
            usleep(50_000);
        } while ((microtime(true) - $start) * 1000 < $waitMs);
        fclose($fp);
        return null;
    }

    public static function release(array $lock): void
    {
        if (!is_resource($lock['handle'] ?? null)) return;
        @flock($lock['handle'], LOCK_UN);
        @fclose($lock['handle']);
        @unlink($lock['path']);
    }

    /** Check whether a lock is currently held (best-effort, no acquire). */
    public static function isHeld(string $name): bool
    {
        $path = self::path($name);
        if (!is_file($path)) return false;
        $fp = @fopen($path, 'r');
        if (!$fp) return false;
        $held = !flock($fp, LOCK_SH | LOCK_NB);
        fclose($fp);
        return $held;
    }
}
