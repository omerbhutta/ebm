<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Cache;
use App\Core\Logger;
use PDO;

final class TenantService
{
    public static function ensureTable(): void
    {
        $pdo = Database::connection();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tenants (
                id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name            VARCHAR(255) NOT NULL,
                tenant_id       VARCHAR(100) NOT NULL DEFAULT '',
                client_id       VARCHAR(255) NOT NULL DEFAULT '',
                client_secret   TEXT DEFAULT NULL,
                is_default      TINYINT(1) NOT NULL DEFAULT 0,
                is_active       TINYINT(1) NOT NULL DEFAULT 1,
                notes           TEXT DEFAULT NULL,
                created_at      DATETIME NOT NULL,
                updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_active (is_active),
                KEY idx_default (is_default)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        // Migration: add is_default column if missing on existing installs
        try {
            $pdo->exec("ALTER TABLE tenants ADD COLUMN `is_default` TINYINT(1) NOT NULL DEFAULT 0 AFTER `client_secret`");
        } catch (\Throwable $e) {}
        // Migration: ensure monitored_mailboxes has tenant_id column
        self::ensureMailboxColumn();
    }

    /**
     * Auto-migrate monitored_mailboxes: add tenant_id column if missing.
     */
    public static function ensureMailboxColumn(): void
    {
        $pdo = Database::connection();
        try {
            $pdo->exec("ALTER TABLE monitored_mailboxes ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id`");
        } catch (\Throwable $e) {}
    }

    /**
     * Get or create the default tenant (backward compat for single-tenant installs).
     * Uses credentials from settings table if available.
     */
    public static function getDefault(): array
    {
        self::ensureTable();
        $pdo = Database::connection();

        $stmt = $pdo->query("SELECT * FROM tenants WHERE is_default = 1 LIMIT 1");
        $row = $stmt->fetch();
        if ($row) return $row;

        // Create default tenant from existing settings
        $name   = 'Default';
        $tid    = (string)(\App\Core\Settings::get('graph_tenant_id', ''));
        $cid    = (string)(\App\Core\Settings::get('graph_client_id', ''));
        $secret = (string)(\App\Core\Settings::get('graph_client_secret', ''));

        $stmt = $pdo->prepare("
            INSERT INTO tenants (name, tenant_id, client_id, client_secret, is_default, is_active, notes, created_at)
            VALUES (?, ?, ?, ?, 1, 1, 'Auto-created default tenant', NOW())
        ");
        $stmt->execute([$name, $tid, $cid, $secret]);
        $id = (int)$pdo->lastInsertId();

        return [
            'id'            => $id,
            'name'          => $name,
            'tenant_id'     => $tid,
            'client_id'     => $cid,
            'client_secret' => $secret,
            'is_default'    => '1',
            'is_active'     => '1',
            'notes'         => 'Auto-created default tenant',
        ];
    }

    public static function all(bool $onlyActive = false): array
    {
        self::ensureTable();
        $pdo = Database::connection();
        $sql = "SELECT t.*, (SELECT COUNT(*) FROM monitored_mailboxes m WHERE m.tenant_id = t.id) AS mailbox_count FROM tenants t" .
               ($onlyActive ? " WHERE t.is_active = 1" : "") .
               " ORDER BY t.is_default DESC, t.name ASC";
        return $pdo->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        self::ensureTable();
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function add(string $name, string $tenantId = '', string $clientId = '', string $clientSecret = '', ?string $notes = null): array
    {
        self::ensureTable();
        $name = trim($name);
        if ($name === '') return ['ok' => false, 'error' => 'Tenant name is required.'];
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("
                INSERT INTO tenants (name, tenant_id, client_id, client_secret, is_default, is_active, notes, created_at)
                VALUES (?, ?, ?, ?, 0, 1, ?, NOW())
            ");
            $stmt->execute([$name, $tenantId, $clientId, $clientSecret, $notes ?: null]);
            $id = (int)$pdo->lastInsertId();
            Logger::info('tenant.added', "Tenant \"{$name}\" added", ['id' => $id]);
            return ['ok' => true, 'id' => $id, 'name' => $name];
        } catch (\PDOException $e) {
            return ['ok' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    public static function update(int $id, array $fields): bool
    {
        $allowed = ['name', 'tenant_id', 'client_id', 'client_secret', 'is_active', 'notes'];
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
        $stmt = $pdo->prepare("UPDATE tenants SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->execute($vals);
        Logger::info('tenant.updated', "Tenant #{$id} updated", ['fields' => array_keys($fields)]);
        return $stmt->rowCount() > 0;
    }

    public static function toggle(int $id): bool
    {
        $pdo = Database::connection();
        $pdo->prepare("UPDATE tenants SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
        $row = self::find($id);
        if ($row) {
            Logger::info('tenant.toggled', "Tenant {$row['name']} " . ((int)$row['is_active'] === 1 ? 'enabled' : 'paused'));
            return true;
        }
        return false;
    }

    public static function remove(int $id): bool
    {
        $row = self::find($id);
        if (!$row) return false;
        // Prevent deleting default tenant
        if ((int)$row['is_default'] === 1) return false;
        $pdo = Database::connection();
        $pdo->prepare("DELETE FROM tenants WHERE id = ?")->execute([$id]);
        // Unassign mailboxes
        $pdo->prepare("UPDATE monitored_mailboxes SET tenant_id = 0 WHERE tenant_id = ?")->execute([$id]);
        Logger::warn('tenant.removed', "Removed tenant {$row['name']}");
        return true;
    }

    public static function setDefault(int $id): bool
    {
        $pdo = Database::connection();
        $pdo->exec("UPDATE tenants SET is_default = 0");
        $stmt = $pdo->prepare("UPDATE tenants SET is_default = 1 WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
