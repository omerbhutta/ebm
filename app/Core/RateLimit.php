<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Simple rate limiter, file-backed in storage/locks.
 * Tracks (key, ip) windows. Used primarily for login attempts.
 */
final class RateLimit
{
    public static function hit(string $key, int $maxAttempts, int $decaySeconds): array
    {
        $file = self::file($key);
        $now = time();
        $data = self::read($file);

        $data = array_filter($data, fn($t) => $t > $now - $decaySeconds);
        $count = count($data);
        $remaining = max(0, $maxAttempts - $count);

        if ($count >= $maxAttempts) {
            $retryAfter = min($data) + $decaySeconds - $now;
            return [
                'limited'     => true,
                'attempts'    => $count,
                'remaining'   => 0,
                'retry_after' => max(1, $retryAfter),
            ];
        }

        $data[] = $now;
        self::write($file, array_values($data));

        return [
            'limited'     => false,
            'attempts'    => $count + 1,
            'remaining'   => $remaining - 1,
            'retry_after' => 0,
        ];
    }

    public static function clear(string $key): void
    {
        $file = self::file($key);
        if (is_file($file)) @unlink($file);
    }

    private static function file(string $key): string
    {
        $dir = App::instance()->storagePath('locks');
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return $dir . DIRECTORY_SEPARATOR . 'rl_' . md5($key) . '.json';
    }

    private static function read(string $file): array
    {
        if (!is_file($file)) return [];
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    private static function write(string $file, array $data): void
    {
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
}
