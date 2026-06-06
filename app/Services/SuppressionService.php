<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Suppression list management — the cross-check table.
 */
final class SuppressionService
{
    public static function sync(array $items): array
    {
        if (empty($items)) return ['added' => 0, 'updated' => 0, 'total' => self::count()];

        $isTuple = isset($items[0]) && is_array($items[0]) && array_key_exists('message_id', $items[0]);
        $pdo = Database::connection();

        if (!$isTuple) {
            $emails = array_values(array_unique(array_map(
                fn($e) => strtolower(trim($e, ".,;:()[]\"'")),
                $items
            )));
            $emails = array_filter($emails, fn($e) => $e !== '' && strpos($e, '@') !== false);
            if (empty($emails)) return ['added' => 0, 'updated' => 0, 'total' => self::count()];

            $ph = implode(',', array_fill(0, count($emails), '?'));
            $s = $pdo->prepare("SELECT email FROM suppression_list WHERE email IN ($ph)");
            $s->execute(array_values($emails));
            $existing = array_flip(array_column($s->fetchAll(), 'email'));

            $ins = $pdo->prepare("
                INSERT INTO suppression_list (email, first_seen, last_seen, bounce_count)
                VALUES (?, NOW(), NOW(), 1)
                ON DUPLICATE KEY UPDATE last_seen = NOW(), bounce_count = bounce_count + 1
            ");
            foreach ($emails as $e) $ins->execute([$e]);

            return [
                'added'   => count($emails) - count($existing),
                'updated' => count($existing),
                'total'   => self::count(),
            ];
        }

        // Tuple form
        $cleaned = [];
        $seen = [];
        foreach ($items as $i) {
            $mb  = strtolower(trim((string)($i['mailbox'] ?? '')));
            $mid = trim((string)($i['message_id'] ?? ''));
            $em  = strtolower(trim((string)($i['email'] ?? ''), ".,;:()[]\"'"));
            if ($mb === '' || $mid === '' || $em === '' || strpos($em, '@') === false) continue;
            $key = $mb . "\x00" . $mid;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $cleaned[] = ['mailbox' => $mb, 'message_id' => $mid, 'email' => $em];
        }
        if (empty($cleaned)) return ['added' => 0, 'updated' => 0, 'total' => self::count()];

        $mailboxes = array_values(array_unique(array_column($cleaned, 'mailbox')));
        $mbPh = implode(',', array_fill(0, count($mailboxes), '?'));
        $stmt = $pdo->prepare("SELECT mailbox_email, message_id FROM processed_ndrs WHERE mailbox_email IN ($mbPh)");
        $stmt->execute($mailboxes);
        $alreadySeen = [];
        foreach ($stmt->fetchAll() as $r) {
            $alreadySeen[$r['mailbox_email'] . "\x00" . $r['message_id']] = true;
        }

        $newItems = [];
        foreach ($cleaned as $i) {
            $key = $i['mailbox'] . "\x00" . $i['message_id'];
            if (!isset($alreadySeen[$key])) $newItems[] = $i;
        }
        if (empty($newItems)) return ['added' => 0, 'updated' => 0, 'total' => self::count()];

        $pdo->beginTransaction();
        try {
            $insSeen = $pdo->prepare("INSERT IGNORE INTO processed_ndrs (mailbox_email, message_id, processed_at) VALUES (?, ?, NOW())");
            $insSup = $pdo->prepare("
                INSERT INTO suppression_list (email, first_seen, last_seen, bounce_count)
                VALUES (?, NOW(), NOW(), 1)
                ON DUPLICATE KEY UPDATE last_seen = NOW(), bounce_count = bounce_count + 1
            ");

            $newEmails = [];
            foreach ($newItems as $i) {
                $insSeen->execute([$i['mailbox'], $i['message_id']]);
                $newEmails[] = $i['email'];
            }
            $newEmails = array_values(array_unique($newEmails));

            $existingEmails = [];
            if (!empty($newEmails)) {
                $ph = implode(',', array_fill(0, count($newEmails), '?'));
                $s = $pdo->prepare("SELECT email FROM suppression_list WHERE email IN ($ph)");
                $s->execute($newEmails);
                $existingEmails = array_flip(array_column($s->fetchAll(), 'email'));
            }
            foreach ($newEmails as $e) $insSup->execute([$e]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return [
            'added'   => count($newEmails) - count($existingEmails),
            'updated' => count($existingEmails),
            'total'   => self::count(),
        ];
    }

    public static function count(): int
    {
        try {
            return (int)Database::connection()->query("SELECT COUNT(*) FROM suppression_list")->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public static function stats(): array
    {
        try {
            $pdo = Database::connection();
            $row = $pdo->query("
                SELECT
                    COUNT(*) AS total,
                    COALESCE(SUM(bounce_count), 0) AS total_bounces,
                    COUNT(CASE WHEN last_seen >= NOW() - INTERVAL 7 DAY THEN 1 END) AS last_7d,
                    COUNT(CASE WHEN last_seen >= NOW() - INTERVAL 1 DAY THEN 1 END) AS last_24h,
                    MIN(first_seen) AS oldest,
                    MAX(last_seen)  AS newest
                FROM suppression_list
            ")->fetch();
            $topDomains = $pdo->query("
                SELECT SUBSTRING_INDEX(email, '@', -1) AS domain, COUNT(*) AS c
                FROM suppression_list
                GROUP BY domain ORDER BY c DESC LIMIT 5
            ")->fetchAll();
            $lastSync = $pdo->query("SELECT MAX(processed_at) FROM processed_ndrs")->fetchColumn();
            return [
                'summary'     => $row ?: [],
                'top_domains' => $topDomains ?: [],
                'last_sync'   => $lastSync ?: null,
            ];
        } catch (\Throwable $e) {
            return ['summary' => [], 'top_domains' => [], 'last_sync' => null];
        }
    }

    public static function domains(): array
    {
        $pdo = Database::connection();
        $rows = $pdo->query("
            SELECT SUBSTRING_INDEX(email, '@', -1) AS domain,
                   COUNT(*) AS total,
                   SUM(bounce_count) AS bounces
            FROM suppression_list
            GROUP BY domain
            ORDER BY total DESC, domain ASC
        ")->fetchAll();
        return array_map(fn($r) => [
            'domain'  => $r['domain'],
            'total'   => (int)$r['total'],
            'bounces' => (int)$r['bounces'],
        ], $rows);
    }

    public static function list(string $search = '', int $page = 1, int $perPage = 50): array
    {
        $pdo = Database::connection();
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE email LIKE ?';
            $params[] = '%' . strtolower($search) . '%';
        }
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM suppression_list $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $listStmt = $pdo->prepare("
            SELECT id, email, first_seen, last_seen, bounce_count
            FROM suppression_list $where
            ORDER BY last_seen DESC
            LIMIT $perPage OFFSET $offset
        ");
        $listStmt->execute($params);
        return [
            'rows'    => $listStmt->fetchAll(),
            'total'   => $total,
            'page'    => $page,
            'pages'   => max(1, (int)ceil($total / $perPage)),
            'per'     => $perPage,
        ];
    }

    public static function check(array $emails): array
    {
        $emails = array_values(array_unique(array_filter(array_map(
            fn($e) => strtolower(trim((string)$e)),
            $emails
        ), fn($e) => $e !== '' && strpos($e, '@') !== false)));

        if (!$emails) return [];

        $pdo = Database::connection();
        $ph = implode(',', array_fill(0, count($emails), '?'));
        $stmt = $pdo->prepare("SELECT email, bounce_count, last_seen FROM suppression_list WHERE email IN ($ph)");
        $stmt->execute($emails);
        $found = [];
        foreach ($stmt->fetchAll() as $r) {
            $found[strtolower($r['email'])] = [
                'bounce_count' => (int)$r['bounce_count'],
                'last_seen'    => $r['last_seen'],
            ];
        }
        return $found;
    }

    public static function add(string $email): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid email address.'];
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            INSERT INTO suppression_list (email, first_seen, last_seen, bounce_count)
            VALUES (?, NOW(), NOW(), 1)
            ON DUPLICATE KEY UPDATE last_seen = NOW(), bounce_count = bounce_count + 1
        ");
        $stmt->execute([$email]);
        Logger::info('suppression.added', "Added {$email}");
        return ['ok' => true, 'email' => $email, 'new' => $stmt->rowCount() === 1];
    }

    public static function remove(int $id): bool
    {
        $pdo = Database::connection();
        $row = $pdo->prepare("SELECT email FROM suppression_list WHERE id = ?");
        $row->execute([$id]);
        $email = $row->fetchColumn();
        if (!$email) return false;
        $pdo->prepare("DELETE FROM suppression_list WHERE id = ?")->execute([$id]);
        Logger::warn('suppression.removed', "Removed {$email}");
        return true;
    }

    public static function clearAll(): int
    {
        $pdo = Database::connection();
        $count = (int)$pdo->query("SELECT COUNT(*) FROM suppression_list")->fetchColumn();
        $pdo->exec("TRUNCATE TABLE suppression_list");
        $pdo->exec("TRUNCATE TABLE processed_ndrs");
        Logger::warn('suppression.cleared', "Cleared {$count} suppression entries");
        return $count;
    }

    public static function resetCounts(): int
    {
        $pdo = Database::connection();
        $count = (int)$pdo->query("SELECT COUNT(*) FROM suppression_list WHERE bounce_count > 1")->fetchColumn();
        if ($count > 0) {
            $pdo->exec("UPDATE suppression_list SET bounce_count = 1 WHERE bounce_count > 1");
            Logger::info('suppression.counts_reset', "Reset bounce counts on {$count} entries");
        }
        return $count;
    }

    public static function purgeProcessedNdrs(): int
    {
        $pdo = Database::connection();
        $count = (int)$pdo->query("SELECT COUNT(*) FROM processed_ndrs")->fetchColumn();
        if ($count > 0) {
            $pdo->exec("TRUNCATE TABLE processed_ndrs");
            Logger::info('suppression.ndrs_purged', "Purged {$count} processed NDR records");
        }
        return $count;
    }

    public static function pruneOlderThan(int $days): int
    {
        if ($days <= 0) return 0;
        $pdo = Database::connection();
        $stmt = $pdo->prepare("DELETE FROM suppression_list WHERE last_seen < (NOW() - INTERVAL ? DAY)");
        $stmt->execute([$days]);
        $n = $stmt->rowCount();
        if ($n > 0) Logger::info('suppression.pruned', "Pruned {$n} entries older than {$days} days");
        return $n;
    }
}
