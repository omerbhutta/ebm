<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Settings — DB-driven key/value store with in-memory cache.
 * Sensitive fields are stored as-is (DB is the trust boundary).
 */
final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) return self::$cache;

        try {
            $pdo = Database::connection();
            $rows = $pdo->query("SELECT `key`, `value` FROM settings")->fetchAll();
        } catch (\Throwable $e) {
            return self::$cache = [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[$r['key']] = $r['value'];
        }
        return self::$cache = $out;
    }

    public static function get(string $key, $default = null)
    {
        $all = self::all();
        return $all[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) return $default;
        return in_array(strtolower((string)$v), ['1','true','yes','on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return $v === null ? $default : (int)$v;
    }

    public static function set(string $key, $value): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            INSERT INTO settings (`key`, `value`, `updated_at`)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()
        ");
        $stmt->execute([$key, (string)$value]);
        if (self::$cache !== null) self::$cache[$key] = (string)$value;
    }

    public static function setMany(array $pairs): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            INSERT INTO settings (`key`, `value`, `updated_at`)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()
        ");
        foreach ($pairs as $k => $v) {
            $stmt->execute([$k, (string)$v]);
            if (self::$cache !== null) self::$cache[$k] = (string)$v;
        }
    }

    public static function forget(string $key): void
    {
        $pdo = Database::connection();
        $pdo->prepare("DELETE FROM settings WHERE `key` = ?")->execute([$key]);
        if (self::$cache !== null) unset(self::$cache[$key]);
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
