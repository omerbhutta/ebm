<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Authentication. Two roles:
 *   - viewer : can view bounce records, suppression list
 *   - admin  : full access including admin panel
 *
 * Stored as: $_SESSION['user'] = ['role' => 'viewer'|'admin', 'login_at' => time(), 'last_active' => time()]
 */
final class Auth
{
    public const ROLE_VIEWER = 'viewer';
    public const ROLE_ADMIN  = 'admin';

    public static function check(): bool
    {
        $u = Session::get('user');
        if (!is_array($u) || empty($u['role'])) return false;

        $ttl = Settings::int('session_ttl', 28800);
        if ($ttl > 0 && time() - (int)($u['last_active'] ?? 0) > $ttl) {
            self::logout();
            return false;
        }
        $u['last_active'] = time();
        Session::set('user', $u);
        return true;
    }

    public static function user(): ?array
    {
        return self::check() ? Session::get('user') : null;
    }

    public static function role(): ?string
    {
        $u = self::user();
        return $u['role'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === self::ROLE_ADMIN;
    }

    public static function isViewer(): bool
    {
        $r = self::role();
        return $r === self::ROLE_VIEWER || $r === self::ROLE_ADMIN;
    }

    public static function attempt(string $password, string $role): bool
    {
        $key = $role === self::ROLE_ADMIN ? 'admin_password_hash' : 'viewer_password_hash';
        $hash = Settings::get($key, '');
        if (!is_string($hash) || $hash === '') return false;
        if (!password_verify($password, $hash)) return false;

        Session::regenerate();
        Session::set('user', [
            'role'        => $role,
            'login_at'    => time(),
            'last_active' => time(),
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
        return true;
    }

    public static function login(string $role): void
    {
        Session::regenerate();
        Session::set('user', [
            'role'        => $role,
            'login_at'    => time(),
            'last_active' => time(),
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    }

    public static function logout(): void
    {
        Session::forget('user');
        Session::regenerate();
    }

    public static function setPassword(string $role, string $plain): void
    {
        $key = $role === self::ROLE_ADMIN ? 'admin_password_hash' : 'viewer_password_hash';
        Settings::set($key, password_hash($plain, PASSWORD_DEFAULT));
    }

    public static function requireViewer(): void
    {
        if (!self::isViewer()) {
            $target = '/login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/');
            Response::redirect(App::instance()->baseUrl($target));
        }
    }

    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            $target = '/admin/login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/admin');
            Response::redirect(App::instance()->baseUrl($target));
        }
    }
}
