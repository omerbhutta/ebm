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
