<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\Settings;
use App\Core\Flash;
use App\Core\Cache;

/**
 * Application-wide system settings: name, footer, theme, retention, cache.
 */
final class SystemController extends Controller
{
    public function index(Request $req): void
    {
        Auth::requireAdmin();
        $this->view('admin/system', [
            's' => Settings::all(),
        ]);
    }

    public function update(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);

        $appName      = trim((string)$req->post('app_name', ''));
        $tagline      = trim((string)$req->post('app_tagline', ''));
        $footerText   = trim((string)$req->post('footer_text', ''));
        $footerUrl    = trim((string)$req->post('footer_url', ''));
        $theme        = in_array($req->post('theme'), ['dark','light'], true) ? $req->post('theme') : 'dark';
        $themeToggle  = $req->post('theme_toggle_enabled') === '1' ? '1' : '0';
        $cacheTtl     = max(0, (int)$req->post('cache_ttl', 300));
        $sessionTtl   = max(300, (int)$req->post('session_ttl', 28800));
        $retention    = max(0, (int)$req->post('retention_days', 0));
        $folders      = (string)$req->post('monitor_folders', '');

        $v = $this->validate([
            'app_name' => $appName,
            'footer_text' => $footerText,
        ], [
            'app_name' => 'required|max:120',
            'footer_text' => 'max:200',
        ]);
        if (!$v->ok()) {
            foreach ($v->errors() as $msgs) foreach ($msgs as $m) Flash::error($m);
            $this->redirect('/admin/system');
        }

        // Validate folders JSON if changed
        if ($folders !== '') {
            $decoded = json_decode($folders, true);
            if (!is_array($decoded)) {
                Flash::error('Monitor folders must be valid JSON: {"Label":"folderid", ...}');
                $this->redirect('/admin/system');
            }
            $folders = json_encode($decoded);
        } else {
            $folders = (string)Settings::get('monitor_folders', json_encode(['Inbox' => 'inbox']));
        }

        Settings::setMany([
            'app_name'             => $appName,
            'app_tagline'          => $tagline,
            'footer_text'          => $footerText,
            'footer_url'           => $footerUrl,
            'theme'                => $theme,
            'theme_toggle_enabled' => $themeToggle,
            'cache_ttl'            => (string)$cacheTtl,
            'session_ttl'          => (string)$sessionTtl,
            'retention_days'       => (string)$retention,
            'monitor_folders'      => $folders,
        ]);
        Flash::success('System settings saved.');
        $this->redirect('/admin/system');
    }

    public function flushCache(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);
        $n = Cache::flush();
        Flash::success("Flushed {$n} cache files.");
        $this->redirect('/admin/system');
    }
}
