<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Database;

/**
 * Handles the installation: writes config, runs schema, seeds defaults.
 */
final class InstallService
{
    public static function checkRequirements(): array
    {
        $php = PHP_VERSION;
        $minPhp = '7.4.0';
        $checks = [
            [
                'name'    => 'PHP Version (>= 7.4)',
                'pass'    => version_compare($php, $minPhp, '>='),
                'current' => $php,
            ],
            [
                'name'    => 'PDO extension',
                'pass'    => extension_loaded('pdo'),
                'current' => extension_loaded('pdo') ? 'loaded' : 'missing',
            ],
            [
                'name'    => 'PDO MySQL driver',
                'pass'    => extension_loaded('pdo_mysql'),
                'current' => extension_loaded('pdo_mysql') ? 'loaded' : 'missing',
            ],
            [
                'name'    => 'cURL extension',
                'pass'    => extension_loaded('curl'),
                'current' => extension_loaded('curl') ? 'loaded' : 'missing',
            ],
            [
                'name'    => 'OpenSSL extension',
                'pass'    => extension_loaded('openssl'),
                'current' => extension_loaded('openssl') ? 'loaded' : 'missing',
            ],
            [
                'name'    => 'mbstring extension',
                'pass'    => extension_loaded('mbstring'),
                'current' => extension_loaded('mbstring') ? 'loaded' : 'missing',
            ],
            [
                'name'    => 'JSON extension',
                'pass'    => extension_loaded('json') || function_exists('json_encode'),
                'current' => function_exists('json_encode') ? 'loaded' : 'missing',
            ],
            [
                'name'    => 'config/ directory writable',
                'pass'    => is_writable(App::instance()->configPath()),
                'current' => is_writable(App::instance()->configPath()) ? 'writable' : 'NOT writable',
            ],
            [
                'name'    => 'storage/ directory writable',
                'pass'    => is_writable(App::instance()->storagePath()),
                'current' => is_writable(App::instance()->storagePath()) ? 'writable' : 'NOT writable',
            ],
            [
                'name'    => 'storage/cache writable',
                'pass'    => is_writable(App::instance()->storagePath('cache')),
                'current' => is_writable(App::instance()->storagePath('cache')) ? 'writable' : 'NOT writable',
            ],
            [
                'name'    => 'storage/logs writable',
                'pass'    => is_writable(App::instance()->storagePath('logs')),
                'current' => is_writable(App::instance()->storagePath('logs')) ? 'writable' : 'NOT writable',
            ],
        ];

        return [
            'checks'  => $checks,
            'all_ok'  => !in_array(false, array_column($checks, 'pass'), true),
        ];
    }

    public static function runSchema(\PDO $pdo): void
    {
        $schemaFile = App::instance()->basePath('database/schema.sql');
        if (!is_file($schemaFile)) {
            throw new \RuntimeException('Schema file not found.');
        }
        $sql = (string)file_get_contents($schemaFile);
        $statements = self::splitStatements($sql);
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') continue;
            $pdo->exec($stmt);
        }
    }

    private static function splitStatements(string $sql): array
    {
        $sql = preg_replace('!/\*.*?\*/!s', '', $sql);
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
        return $statements ?: [];
    }

    public static function seedDefaults(\PDO $pdo, array $config): void
    {
        $defaults = [
            'app_name'              => 'Email Bounce Monitor',
            'app_tagline'           => 'Track undeliverable email reports across all your mailboxes',
            'footer_text'           => 'Powered by E-Services 360',
            'footer_url'            => 'https://eservices360.com',
            'theme'                 => 'dark',
            'theme_toggle_enabled'  => '1',
            'session_ttl'           => '28800',
            'cache_ttl'             => '300',
            'retention_days'        => '0',
            'login_rate_max'        => '5',
            'login_rate_window'     => '900',
            'check_api_key'         => bin2hex(random_bytes(20)),
            'graph_tenant_id'       => '',
            'graph_client_id'       => '',
            'graph_client_secret'   => '',
            'monitor_folders'       => json_encode(['Inbox' => 'inbox','Junk Email' => 'junkemail','Deleted Items' => 'deleteditems']),
            'installed_at'          => date('Y-m-d H:i:s'),
            'app_version'           => '2.0.0',
        ];

        $stmt = $pdo->prepare("
            INSERT INTO settings (`key`, `value`, `updated_at`)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()
        ");
        foreach ($defaults as $k => $v) {
            $stmt->execute([$k, (string)$v]);
        }
    }

    public static function writeConfig(array $database): bool
    {
        $path = App::instance()->configPath('installed.php');
        $tpl = <<<PHP
<?php
/**
 * EBM — Installed configuration.
 * Auto-generated by the installer. DO NOT edit manually unless you know what you're doing.
 * All application settings live in the `settings` database table.
 */
return [
    'database' => [
        'host'    => %s,
        'port'    => %d,
        'name'    => %s,
        'user'    => %s,
        'pass'    => %s,
        'charset' => %s,
    ],
    'installed_at' => %s,
    'version'      => %s,
];
PHP;
        $content = sprintf(
            $tpl,
            var_export((string)$database['host'], true),
            (int)$database['port'],
            var_export((string)$database['name'], true),
            var_export((string)$database['user'], true),
            var_export((string)$database['pass'], true),
            var_export((string)($database['charset'] ?? 'utf8mb4'), true),
            var_export(date('Y-m-d H:i:s'), true),
            var_export('2.0.0', true)
        );

        return file_put_contents($path, $content) !== false;
    }

    public static function lock(): bool
    {
        $path = App::instance()->storagePath('locks/install.lock');
        return file_put_contents($path, json_encode([
            'installed_at' => date('Y-m-d H:i:s'),
            'php_version'  => PHP_VERSION,
            'app_version'  => '2.0.0',
        ])) !== false;
    }

    /**
     * The full production .htaccess written at the end of a successful install.
     * The shipping .htaccess in the repo is intentionally minimal so the installer
     * can always load — even on restrictive Apache setups. After install completes,
     * the installer overwrites it with this hardened version.
     */
    public const HTACCESS_PRODUCTION = <<<'HTACCESS'
# Email Bounce Monitor (EBM) — production .htaccess
# Auto-written by the installer. Safe to customise; if you delete it,
# the next install (or a manual run of InstallService::writeHtaccess())
# will restore this version.

Options -Indexes -MultiViews
DirectoryIndex index.php

# URL rewriting — clean URLs, hide .php
<IfModule mod_rewrite.c>
    RewriteEngine On

    # /something.php → /something (301), keep /check.php as legacy shim
    RewriteCond %{THE_REQUEST} \s/+([^\s]+?)\.php[\s?] [NC]
    RewriteCond %{REQUEST_URI} !/check\.php$ [NC]
    RewriteRule ^ /%1 [R=301,L,NE]

    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]

    RewriteRule ^ index.php [L,QSA]
</IfModule>

# Block framework folders (path-relative, works in subfolder installs)
RedirectMatch 404 ^/?app/
RedirectMatch 404 ^/?config/
RedirectMatch 404 ^/?database/
RedirectMatch 404 ^/?storage/

# Block dotfiles + sensitive extensions
<FilesMatch "^\.">
    Require all denied
</FilesMatch>
<FilesMatch "\.(sql|log|sh|env|ini|conf|inc|bak)$">
    Require all denied
</FilesMatch>
<Files "README.md">
    Require all denied
</Files>

# Security headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 7 days"
    ExpiresByType application/javascript "access plus 7 days"
    ExpiresByType image/svg+xml "access plus 30 days"
    ExpiresByType image/png "access plus 30 days"
    ExpiresByType image/jpeg "access plus 30 days"
</IfModule>

# Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json
</IfModule>
HTACCESS;

    /**
     * Write the production .htaccess. Called at the end of a successful install.
     * If it fails (e.g. file is read-only on the prod host), the install still
     * succeeds and a warning is logged — the app is fully functional with the
     * minimal shipping htaccess too.
     */
    public static function writeHtaccess(): bool
    {
        $path = App::instance()->basePath('.htaccess');
        $content = self::HTACCESS_PRODUCTION;
        // Always normalise to LF + no BOM, regardless of how the existing file
        // was checked out.
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        $content = str_replace("\r\n", "\n", $content);
        $ok = @file_put_contents($path, $content) !== false;
        if ($ok) {
            @chmod($path, 0644);
        }
        return $ok;
    }

    public static function isLocked(): bool
    {
        return is_file(App::instance()->storagePath('locks/install.lock'))
            && is_file(App::instance()->configPath('installed.php'));
    }

    public static function unlock(): bool
    {
        $a = @unlink(App::instance()->storagePath('locks/install.lock'));
        $b = @unlink(App::instance()->configPath('installed.php'));
        return $a || $b;
    }
}
