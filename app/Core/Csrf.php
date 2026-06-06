<?php
declare(strict_types=1);

namespace App\Core;

/**
 * CSRF protection.
 */
final class Csrf
{
    public static function token(): string
    {
        Session::start();
        $t = Session::get('_csrf');
        if (!is_string($t) || strlen($t) !== 64) {
            $t = bin2hex(random_bytes(32));
            Session::set('_csrf', $t);
        }
        return $t;
    }

    public static function verify(?string $token): bool
    {
        if (!is_string($token) || $token === '') return false;
        $stored = Session::get('_csrf');
        if (!is_string($stored) || $stored === '') return false;
        return hash_equals($stored, $token);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function check(Request $req): void
    {
        if (!$req->isPost()) return;
        $token = $req->post('_csrf');
        if (!is_string($token)) $token = $req->header('X-CSRF-Token');
        if (!self::verify($token)) {
            http_response_code(419);
            if ($req->isAjax() || $req->isJson()) {
                Response::json(['error' => 'CSRF token mismatch'], 419);
            }
            echo '<h1>419 — Page Expired</h1><p>Your session expired or the form was tampered with. Please reload and try again.</p>';
            exit;
        }
    }
}
