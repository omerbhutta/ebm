<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Activity logger — writes to DB table `activity_log` and rotating file.
 */
final class Logger
{
    public const LEVEL_INFO  = 'info';
    public const LEVEL_WARN  = 'warning';
    public const LEVEL_ERROR = 'error';
    public const LEVEL_AUTH  = 'auth';

    public static function log(string $level, string $event, string $message = '', array $context = []): void
    {
        // File log (always works, even before DB is up)
        self::file($level, $event, $message, $context);

        // DB log
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("
                INSERT INTO activity_log (level, event, message, context, ip, user_agent, user_role, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $level,
                $event,
                $message,
                json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? '',
                substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
                Auth::role() ?? 'guest',
            ]);
        } catch (\Throwable $e) {
            // DB might not be installed yet; file log captured it.
        }
    }

    public static function info(string $event, string $message = '', array $context = []): void
    {
        self::log(self::LEVEL_INFO, $event, $message, $context);
    }

    public static function warn(string $event, string $message = '', array $context = []): void
    {
        self::log(self::LEVEL_WARN, $event, $message, $context);
    }

    public static function error(string $event, string $message = '', array $context = []): void
    {
        self::log(self::LEVEL_ERROR, $event, $message, $context);
    }

    public static function auth(string $event, string $message = '', array $context = []): void
    {
        self::log(self::LEVEL_AUTH, $event, $message, $context);
    }

    private static function file(string $level, string $event, string $message, array $context): void
    {
        try {
            $dir = App::instance()->storagePath('logs');
        } catch (\Throwable $e) {
            return;
        }
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $file = $dir . DIRECTORY_SEPARATOR . 'app-' . date('Y-m-d') . '.log';
        $line = sprintf(
            "[%s] %s.%s: %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $event,
            $message,
            !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES) : ''
        );
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function recent(int $limit = 100, ?string $level = null): array
    {
        try {
            $pdo = Database::connection();
            if ($level) {
                $stmt = $pdo->prepare("SELECT * FROM activity_log WHERE level = ? ORDER BY id DESC LIMIT " . (int)$limit);
                $stmt->execute([$level]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM activity_log ORDER BY id DESC LIMIT " . (int)$limit);
                $stmt->execute();
            }
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function prune(int $days): int
    {
        if ($days <= 0) return 0;
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("DELETE FROM activity_log WHERE created_at < (NOW() - INTERVAL ? DAY)");
            $stmt->execute([$days]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
