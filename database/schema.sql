-- ============================================================
-- Email Bounce Monitor (EBM) — Database Schema v2.0.0
-- ============================================================

-- Application settings (key/value)
CREATE TABLE IF NOT EXISTS settings (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key`        VARCHAR(100) NOT NULL,
    `value`      LONGTEXT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suppression list — one row per failed recipient (cross-check before sending)
CREATE TABLE IF NOT EXISTS suppression_list (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(255) NOT NULL,
    first_seen    DATETIME NOT NULL,
    last_seen     DATETIME NOT NULL,
    bounce_count  INT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uk_email (email),
    KEY idx_last_seen (last_seen),
    KEY idx_bounce_count (bounce_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Processed NDR tracking (so re-syncing doesn't inflate bounce_count)
CREATE TABLE IF NOT EXISTS processed_ndrs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mailbox_email VARCHAR(255) NOT NULL,
    message_id    VARCHAR(255) NOT NULL,
    processed_at  DATETIME NOT NULL,
    UNIQUE KEY uk_msg (mailbox_email, message_id),
    KEY idx_processed_at (processed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monitored mailboxes (admins manage which inboxes are scanned)
CREATE TABLE IF NOT EXISTS monitored_mailboxes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(255) NOT NULL,
    description     VARCHAR(255) DEFAULT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      DATETIME NOT NULL,
    last_synced_at  DATETIME DEFAULT NULL,
    last_error      TEXT DEFAULT NULL,
    UNIQUE KEY uk_email (email),
    KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity log (auth attempts, settings changes, etc.)
CREATE TABLE IF NOT EXISTS activity_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level       VARCHAR(16) NOT NULL DEFAULT 'info',
    event       VARCHAR(80) NOT NULL,
    message     TEXT NULL,
    context     LONGTEXT NULL,
    ip          VARCHAR(64) NULL,
    user_agent  VARCHAR(255) NULL,
    user_role   VARCHAR(20) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_created (created_at),
    KEY idx_event (event),
    KEY idx_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily scan statistics (feeds the dashboard "Total Mails Scanned" + 7-day trend).
-- One row per calendar day. Latest scan wins (UPSERT replaces the count
-- instead of accumulating) so repeated dashboard refreshes with no new mail
-- do NOT inflate the number.
--
--  messages_scanned  = total messages returned by the Graph query
--  unique_failed     = number of distinct failed email addresses extracted
--  bounce_messages   = messages that had at least one failure (for hit rate)
CREATE TABLE IF NOT EXISTS scan_stats (
    `day`              DATE         NOT NULL,
    `messages_scanned` INT UNSIGNED NOT NULL DEFAULT 0,
    `unique_failed`    INT UNSIGNED NOT NULL DEFAULT 0,
    `bounce_messages`  INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
