<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Database connection (PDO singleton).
 * Loads connection details from /config/installed.php after installation.
 */
final class Database
{
    private static ?\PDO $pdo = null;

    public static function connection(): \PDO
    {
        if (self::$pdo instanceof \PDO) return self::$pdo;

        $cfg = self::loadConfig();
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'], (int)$cfg['port'], $cfg['name'], $cfg['charset']
        );

        self::$pdo = new \PDO($dsn, $cfg['user'], $cfg['pass'], [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$pdo;
    }

    public static function tryConnect(array $cfg): array
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;charset=%s',
                $cfg['host'] ?? 'localhost',
                (int)($cfg['port'] ?? 3306),
                $cfg['charset'] ?? 'utf8mb4'
            );
            $pdo = new \PDO($dsn, $cfg['user'] ?? '', $cfg['pass'] ?? '', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);

            $name = trim((string)($cfg['name'] ?? ''));
            if ($name === '') {
                return ['ok' => false, 'error' => 'Database name is required.'];
            }

            // Try to use the database; create if needed
            try {
                $pdo->exec("USE `" . str_replace('`', '``', $name) . "`");
                $dbExists = true;
            } catch (\PDOException $e) {
                $dbExists = false;
            }

            if (!$dbExists) {
                try {
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '``', $name) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo->exec("USE `" . str_replace('`', '``', $name) . "`");
                } catch (\PDOException $e) {
                    return ['ok' => false, 'error' => 'Cannot create database: ' . $e->getMessage()];
                }
            }

            return ['ok' => true, 'created' => !$dbExists];
        } catch (\PDOException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public static function fresh(array $cfg): \PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'], (int)$cfg['port'], $cfg['name'], $cfg['charset'] ?? 'utf8mb4'
        );
        return new \PDO($dsn, $cfg['user'], $cfg['pass'], [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    private static function loadConfig(): array
    {
        $path = App::instance()->configPath('installed.php');
        if (!is_file($path)) {
            throw new \RuntimeException('Application is not installed.');
        }
        $cfg = require $path;
        if (!is_array($cfg) || empty($cfg['database'])) {
            throw new \RuntimeException('Invalid configuration.');
        }
        $db = $cfg['database'];
        return [
            'host'    => $db['host']    ?? 'localhost',
            'port'    => $db['port']    ?? 3306,
            'name'    => $db['name']    ?? '',
            'user'    => $db['user']    ?? '',
            'pass'    => $db['pass']    ?? '',
            'charset' => $db['charset'] ?? 'utf8mb4',
        ];
    }
}
