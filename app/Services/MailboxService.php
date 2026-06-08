<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Cache;
use App\Core\Logger;

/**
 * Manages monitored_mailboxes table.
 */
final class MailboxService
{
    public static function slug(string $email): string
    {
        return preg_replace('/[^a-z0-9]/', '_', strtolower($email));
    }

    public static function all(bool $onlyActive = false): array
    {
        $pdo = Database::connection();
        try {
            $sql = "SELECT m.*, t.name AS tenant_name FROM monitored_mailboxes m LEFT JOIN tenants t ON m.tenant_id = t.id" .
                   ($onlyActive ? " WHERE m.is_active = 1" : "") . " ORDER BY m.id";
            return $pdo->query($sql)->fetchAll();
        } catch (\PDOException $e) {
            // tenants table may not exist yet (existing install pre-migration)
            $sql = "SELECT * FROM monitored_mailboxes" . ($onlyActive ? " WHERE is_active = 1" : "") . " ORDER BY id";
            return $pdo->query($sql)->fetchAll();
        }
    }

    public static function allByTenant(int $tenantId, bool $onlyActive = false): array
    {
        $pdo = Database::connection();
        $sql = "SELECT m.*, t.name AS tenant_name FROM monitored_mailboxes m LEFT JOIN tenants t ON m.tenant_id = t.id WHERE m.tenant_id = ?" .
               ($onlyActive ? " AND m.is_active = 1" : "") . " ORDER BY m.id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::connection();
        try {
            $stmt = $pdo->prepare("SELECT m.*, t.name AS tenant_name FROM monitored_mailboxes m LEFT JOIN tenants t ON m.tenant_id = t.id WHERE m.id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch() ?: null;
        } catch (\PDOException $e) {
            $stmt = $pdo->prepare("SELECT * FROM monitored_mailboxes WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch() ?: null;
        }
    }

    public static function findByEmail(string $email): ?array
    {
        $pdo = Database::connection();
        try {
            $stmt = $pdo->prepare("SELECT m.*, t.name AS tenant_name FROM monitored_mailboxes m LEFT JOIN tenants t ON m.tenant_id = t.id WHERE m.email = ?");
            $stmt->execute([strtolower(trim($email))]);
            return $stmt->fetch() ?: null;
        } catch (\PDOException $e) {
            $stmt = $pdo->prepare("SELECT * FROM monitored_mailboxes WHERE email = ?");
            $stmt->execute([strtolower(trim($email))]);
            return $stmt->fetch() ?: null;
        }
    }

    public static function add(string $email, ?string $description = null, int $tenantId = 0): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid email address.'];
        }
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("
                INSERT INTO monitored_mailboxes (tenant_id, email, description, is_active, created_at)
                VALUES (?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$tenantId, $email, $description ?: null]);
            self::invalidateCache($email);
            Logger::info('mailbox.added', "Added mailbox {$email}");
            return ['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'email' => $email];
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'error' => 'This mailbox is already in the list.'];
            }
            return ['ok' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    public static function update(int $id, array $fields): bool
    {
        $allowed = ['email','description','is_active','tenant_id'];
        $sets = [];
        $vals = [];
        foreach ($fields as $k => $v) {
            if (!in_array($k, $allowed, true)) continue;
            $sets[] = "`$k` = ?";
            $vals[] = $v;
        }
        if (!$sets) return false;
        $vals[] = $id;

        $pdo = Database::connection();
        $stmt = $pdo->prepare("UPDATE monitored_mailboxes SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->execute($vals);

        $row = self::find($id);
        if ($row) self::invalidateCache($row['email']);
        Logger::info('mailbox.updated', "Updated mailbox #{$id}", ['fields' => array_keys($fields)]);
        return $stmt->rowCount() > 0;
    }

    public static function toggle(int $id): bool
    {
        $pdo = Database::connection();
        $pdo->prepare("UPDATE monitored_mailboxes SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
        $row = self::find($id);
        if ($row) {
            self::invalidateCache($row['email']);
            Logger::info('mailbox.toggled', "Mailbox {$row['email']} " . ((int)$row['is_active'] === 1 ? 'enabled' : 'paused'));
            return true;
        }
        return false;
    }

    public static function remove(int $id): bool
    {
        $row = self::find($id);
        if (!$row) return false;
        $pdo = Database::connection();
        $pdo->prepare("DELETE FROM monitored_mailboxes WHERE id = ?")->execute([$id]);
        self::invalidateCache($row['email']);
        Logger::warn('mailbox.removed', "Removed mailbox {$row['email']}");
        return true;
    }

    public static function recordSync(string $email, ?string $error = null, ?int $tenantId = null): void
    {
        $pdo = Database::connection();
        if ($tenantId) {
            $stmt = $pdo->prepare("
                UPDATE monitored_mailboxes
                SET last_synced_at = NOW(), last_error = ?
                WHERE email = ? AND tenant_id = ?
            ");
            $stmt->execute([$error, $email, $tenantId]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE monitored_mailboxes
                SET last_synced_at = NOW(), last_error = ?
                WHERE email = ?
            ");
            $stmt->execute([$error, $email]);
        }
    }

    public static function invalidateCache(string $email): void
    {
        $slug = self::slug($email);
        Cache::forgetPattern($slug . '__*.cache');
        Cache::forgetPattern('body_*.cache');
    }
}
