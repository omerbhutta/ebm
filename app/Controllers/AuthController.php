<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Logger;
use App\Core\RateLimit;
use App\Core\Settings;

/**
 * Viewer + Admin login/logout.
 */
final class AuthController extends Controller
{
    public function showLogin(Request $req): void
    {
        if (Auth::check() && Auth::isViewer()) {
            $this->redirect('/dashboard');
        }
        $next = (string)$req->query('next', '');
        $this->view('auth/login', [
            'next'  => $next,
            'errors' => [],
            'mode'  => 'viewer',
        ], 'layouts/auth');
    }

    public function login(Request $req): void
    {
        $this->verifyCsrf($req);

        $ip = $req->ip();
        $key = 'login:viewer:' . $ip;
        $max = Settings::int('login_rate_max', 5);
        $win = Settings::int('login_rate_window', 900);

        $rl = RateLimit::hit($key, $max, $win);
        if ($rl['limited']) {
            Logger::warn('auth.rate_limited', 'Viewer login rate limited', ['ip' => $ip]);
            Flash::error('Too many login attempts. Try again in ' . $rl['retry_after'] . ' seconds.');
            $this->redirect('/login');
        }

        $password = (string)$req->post('password', '');
        if (Auth::attempt($password, Auth::ROLE_VIEWER)) {
            RateLimit::clear($key);
            Logger::auth('auth.login.viewer', 'Viewer signed in', ['ip' => $ip]);
            $next = (string)$req->post('next', '/dashboard');
            if (!preg_match('#^/[^/].*#', $next)) $next = '/dashboard';
            $this->redirect($next);
        }

        Logger::warn('auth.failed.viewer', 'Failed viewer login', ['ip' => $ip]);
        Flash::error('Invalid password.');
        $this->redirect('/login');
    }

    public function showAdminLogin(Request $req): void
    {
        if (Auth::check() && Auth::isAdmin()) {
            $this->redirect('/admin');
        }
        $this->view('auth/login', [
            'next'   => (string)$req->query('next', ''),
            'errors' => [],
            'mode'   => 'admin',
        ], 'layouts/auth');
    }

    public function adminLogin(Request $req): void
    {
        $this->verifyCsrf($req);

        $ip = $req->ip();
        $key = 'login:admin:' . $ip;
        $max = Settings::int('login_rate_max', 5);
        $win = Settings::int('login_rate_window', 900);

        $rl = RateLimit::hit($key, $max, $win);
        if ($rl['limited']) {
            Logger::warn('auth.rate_limited', 'Admin login rate limited', ['ip' => $ip]);
            Flash::error('Too many login attempts. Try again in ' . $rl['retry_after'] . ' seconds.');
            $this->redirect('/admin/login');
        }

        $password = (string)$req->post('password', '');
        if (Auth::attempt($password, Auth::ROLE_ADMIN)) {
            RateLimit::clear($key);
            Logger::auth('auth.login.admin', 'Administrator signed in', ['ip' => $ip]);
            $next = (string)$req->post('next', '/admin');
            if (!preg_match('#^/[^/].*#', $next)) $next = '/admin';
            $this->redirect($next);
        }

        Logger::warn('auth.failed.admin', 'Failed admin login', ['ip' => $ip]);
        Flash::error('Invalid password.');
        $this->redirect('/admin/login');
    }

    public function logout(Request $req): void
    {
        $role = Auth::role();
        Auth::logout();
        if ($role) Logger::auth('auth.logout', "{$role} signed out");
        Flash::info('You have been signed out.');
        $this->redirect('/login');
    }
}
