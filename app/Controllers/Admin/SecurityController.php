<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\Settings;
use App\Core\Flash;
use App\Core\Logger;

/**
 * Security settings — passwords, rate limit, API key, etc.
 */
final class SecurityController extends Controller
{
    public function index(Request $req): void
    {
        Auth::requireAdmin();
        $this->view('admin/security', [
            's' => Settings::all(),
        ]);
    }

    public function changePassword(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);

        $role     = (string)$req->post('role', '');
        $current  = (string)$req->post('current_password', '');
        $newPw    = (string)$req->post('new_password', '');
        $confirm  = (string)$req->post('confirm_password', '');

        if (!in_array($role, [Auth::ROLE_VIEWER, Auth::ROLE_ADMIN], true)) {
            Flash::error('Invalid role.');
            $this->redirect('/admin/security');
        }

        $minLen = $role === Auth::ROLE_ADMIN ? 10 : 8;
        $v = $this->validate(compact('newPw','confirm','current'), [
            'current' => 'required',
            'newPw'   => 'required|min:' . $minLen,
            'confirm' => 'required|match:newPw',
        ]);
        if (!$v->ok()) {
            foreach ($v->errors() as $msgs) foreach ($msgs as $m) Flash::error($m);
            $this->redirect('/admin/security');
        }

        // Verify current admin password before allowing any password change.
        $adminHash = (string)Settings::get('admin_password_hash', '');
        if (!password_verify($current, $adminHash)) {
            Flash::error('Current admin password is incorrect.');
            $this->redirect('/admin/security');
        }

        Auth::setPassword($role, $newPw);
        Logger::auth('password.changed', "Password changed for {$role}");
        Flash::success(ucfirst($role) . ' password updated.');
        $this->redirect('/admin/security');
    }

    public function rotateApiKey(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);
        $current = (string)$req->post('current_password', '');
        $adminHash = (string)Settings::get('admin_password_hash', '');
        if (!password_verify($current, $adminHash)) {
            Flash::error('Current admin password is incorrect.');
            $this->redirect('/admin/security');
        }
        $newKey = bin2hex(random_bytes(20));
        Settings::set('check_api_key', $newKey);
        Logger::auth('api_key.rotated', 'API key rotated');
        Flash::success('API key rotated. The new key is shown on this page — copy it now.');
        $this->redirect('/admin/security');
    }

    public function updateLimits(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);
        $max = max(1, min(50, (int)$req->post('login_rate_max', 5)));
        $win = max(60, min(86400, (int)$req->post('login_rate_window', 900)));
        Settings::setMany([
            'login_rate_max'    => (string)$max,
            'login_rate_window' => (string)$win,
        ]);
        Flash::success('Rate limit settings saved.');
        $this->redirect('/admin/security');
    }
}
