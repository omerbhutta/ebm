<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Database;

/**
 * Daily scan statistics — feeds "Total Mails Scanned", "Suppression Bounces
 * Detected", and "Ingestion Hit Rate" on the dashboard.
 *
 * One row per calendar day. UPSERT **replaces** (never increments), so
 * repeated dashboard refreshes with no new mail keep the same value.
 * Only the dashboard (non-windowed mode) writes to this table; cron does not.
 */
final class ScanStats
{
    public static function ensureTable(): void
    {
        $pdo = Database::connection();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS scan_stats (
                `day`              DATE         NOT NULL,
                `messages_scanned` INT UNSIGNED NOT NULL DEFAULT 0,
                `unique_failed`    INT UNSIGNED NOT NULL DEFAULT 0,
                `bounce_messages`  INT UNSIGNED NOT NULL DEFAULT 0,
                `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`day`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Migration: add columns that may be missing on existing installs.
        // Prior to this migration, scan_stats only had day + messages_scanned.
        try {
            $pdo->exec("ALTER TABLE scan_stats ADD COLUMN `unique_failed` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `messages_scanned`");
        } catch (\Throwable $e) {
            // Column already exists — swallow the error.
        }
        try {
            $pdo->exec("ALTER TABLE scan_stats ADD COLUMN `bounce_messages` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `unique_failed`");
        } catch (\Throwable $e) {
            // Column already exists — swallow the error.
        }
    }

    /**
     * Record today's scan data. Called on every dashboard load
     * (cache hit or cache miss — UPSERT replace is safe either way).
     *
     * @param int $scanned  Total messages returned by Graph
     * @param int $failed   Unique failed email addresses extracted
     * @param int $bounced  Distinct messages that had ≥1 failure
     */
    public static function recordToday(int $scanned, int $failed, int $bounced): void
    {
        self::ensureTable();
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            INSERT INTO scan_stats (`day`, `messages_scanned`, `unique_failed`, `bounce_messages`)
            VALUES (CURDATE(), ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                `messages_scanned` = VALUES(`messages_scanned`),
                `unique_failed`    = VALUES(`unique_failed`),
                `bounce_messages`  = VALUES(`bounce_messages`)
        ");
        $stmt->execute([max(0, $scanned), max(0, $failed), max(0, $bounced)]);
    }

    /**
     * Return last $days days of scan data as a date→map (all 0-filled).
     *
     * @return array<string,array{scanned:int,failed:int,bounced:int}>
     */
    public static function daily(int $days = 7): array
    {
        self::ensureTable();
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT `day`, `messages_scanned`, `unique_failed`, `bounce_messages`
            FROM scan_stats
            WHERE `day` >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY `day` ASC
        ");
        $stmt->execute([max(0, $days - 1)]);
        $map = [];
        foreach ($stmt->fetchAll() as $r) {
            $map[(string)$r['day']] = [
                'scanned' => (int)$r['messages_scanned'],
                'failed'  => (int)$r['unique_failed'],
                'bounced' => (int)$r['bounce_messages'],
            ];
        }
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $out[$d] = $map[$d] ?? ['scanned' => 0, 'failed' => 0, 'bounced' => 0];
        }
        return $out;
    }
}
