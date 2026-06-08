<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\App;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Auth;
use App\Core\Flash;
use App\Services\InstallService;
use App\Services\GraphService;
use App\Services\TenantService;

/**
 * 5-step installation wizard.
 *   step 1 — Requirements
 *   step 2 — Database
 *   step 3 — Graph API
 *   step 4 — Security (passwords + app name)
 *   step 5 — Done
 */
final class InstallController extends Controller
{
    private const SESSION_KEY = '_install';

    public function welcome(Request $req): void
    {
        $this->guardAlreadyInstalled();
        $this->step('welcome', []);
    }

    public function requirements(Request $req): void
    {
        $this->guardAlreadyInstalled();
        $result = InstallService::checkRequirements();
        if ($req->isPost() && $result['all_ok']) {
            $this->mark(1);
            $this->redirect('/install/database');
        }
        $this->step('requirements', $result);
    }

    public function database(Request $req): void
    {
        $this->guardAlreadyInstalled();
        $this->requireStep(1);

        $data = $this->getProgress('database', [
            'host' => 'localhost', 'port' => 3306,
            'name' => 'undeliveredemails', 'user' => 'root', 'pass' => '',
            'charset' => 'utf8mb4',
        ]);
        $errors = [];

        if ($req->isPost()) {
            $data = [
                'host'    => trim((string)$req->post('host','localhost')),
                'port'    => (int)$req->post('port', 3306),
                'name'    => trim((string)$req->post('name','')),
                'user'    => trim((string)$req->post('user','')),
                'pass'    => (string)$req->post('pass',''),
                'charset' => 'utf8mb4',
            ];
            $action = $req->post('action');
            $test = Database::tryConnect($data);

            if ($action === 'test') {
                if ($test['ok']) {
                    Flash::success('Connection successful' . (!empty($test['created']) ? ' (database created)' : '') . '.');
                } else {
                    Flash::error('Connection failed: ' . $test['error']);
                }
                $this->setProgress('database', $data);
                $this->redirect('/install/database');
            }

            if (!$test['ok']) {
                $errors[] = 'Connection failed: ' . $test['error'];
            } else {
                $this->setProgress('database', $data);
                $this->mark(2);
                $this->redirect('/install/graph');
            }
        }

        $this->step('database', array_merge($data, ['errors' => $errors]));
    }

    public function graph(Request $req): void
    {
        $this->guardAlreadyInstalled();
        $this->requireStep(2);

        $data = $this->getProgress('graph', [
            'tenant_id' => '', 'client_id' => '', 'client_secret' => '',
        ]);
        $errors = [];

        if ($req->isPost()) {
            $data = [
                'tenant_id'     => trim((string)$req->post('tenant_id','')),
                'client_id'     => trim((string)$req->post('client_id','')),
                'client_secret' => trim((string)$req->post('client_secret','')),
            ];
            $action = $req->post('action');

            $v = $this->validate($data, [
                'tenant_id'     => 'required|uuid',
                'client_id'     => 'required|uuid',
                'client_secret' => 'required|min:8',
            ]);
            if (!$v->ok()) {
                foreach ($v->errors() as $msgs) foreach ($msgs as $m) $errors[] = $m;
                $this->step('graph', array_merge($data, ['errors' => $errors]));
                return;
            }

            $test = GraphService::testCredentials($data['tenant_id'], $data['client_id'], $data['client_secret']);

            if ($action === 'test') {
                if ($test['ok']) {
                    Flash::success('Graph API connection succeeded. Access token acquired.');
                } else {
                    Flash::error('Graph API failed: ' . $test['error']);
                }
                $this->setProgress('graph', $data);
                $this->redirect('/install/graph');
            }

            if (!$test['ok']) {
                $errors[] = 'Graph API verification failed: ' . $test['error'];
            } else {
                $this->setProgress('graph', $data);
                $this->mark(3);
                $this->redirect('/install/security');
            }
        }

        $this->step('graph', array_merge($data, ['errors' => $errors]));
    }

    public function security(Request $req): void
    {
        $this->guardAlreadyInstalled();
        $this->requireStep(3);

        $data = $this->getProgress('security', [
            'app_name'        => 'Email Bounce Monitor',
            'viewer_password' => '',
            'admin_password'  => '',
            'viewer_confirm'  => '',
            'admin_confirm'   => '',
        ]);
        $errors = [];

        if ($req->isPost()) {
            $data = [
                'app_name'         => trim((string)$req->post('app_name','Email Bounce Monitor')),
                'viewer_password'  => (string)$req->post('viewer_password',''),
                'viewer_confirm'   => (string)$req->post('viewer_confirm',''),
                'admin_password'   => (string)$req->post('admin_password',''),
                'admin_confirm'    => (string)$req->post('admin_confirm',''),
            ];
            $v = $this->validate($data, [
                'app_name'        => 'required|max:120',
                'viewer_password' => 'required|min:8',
                'viewer_confirm'  => 'required|match:viewer_password',
                'admin_password'  => 'required|min:10',
                'admin_confirm'   => 'required|match:admin_password',
            ]);
            if (!$v->ok()) {
                foreach ($v->errors() as $msgs) foreach ($msgs as $m) $errors[] = $m;
            }

            if ($data['viewer_password'] === $data['admin_password']) {
                $errors[] = 'Viewer and admin passwords must be different.';
            }

            if (empty($errors)) {
                $this->setProgress('security', $data);
                $this->mark(4);
                $this->redirect('/install/finish');
            }
        }

        $this->step('security', array_merge($data, ['errors' => $errors]));
    }

    public function finish(Request $req): void
    {
        $this->guardAlreadyInstalled();
        $this->requireStep(4);

        $errors = [];
        try {
            $db   = $this->getProgress('database', []);
            $graph = $this->getProgress('graph', []);
            $sec   = $this->getProgress('security', []);

            // Write installed.php
            if (!InstallService::writeConfig($db)) {
                throw new \RuntimeException('Cannot write config/installed.php. Check permissions.');
            }

            // Connect, run schema, seed
            $pdo = Database::fresh($db + ['charset' => 'utf8mb4']);
            InstallService::runSchema($pdo);
            InstallService::seedDefaults($pdo, []);

            // Save Graph credentials and security settings
            $stmt = $pdo->prepare("
                INSERT INTO settings (`key`,`value`,`updated_at`) VALUES (?,?,NOW())
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()
            ");
            $stmt->execute(['graph_tenant_id', $graph['tenant_id']]);
            $stmt->execute(['graph_client_id', $graph['client_id']]);
            $stmt->execute(['graph_client_secret', $graph['client_secret']]);
            $stmt->execute(['app_name', $sec['app_name']]);
            $stmt->execute(['viewer_password_hash', password_hash($sec['viewer_password'], PASSWORD_DEFAULT)]);
            $stmt->execute(['admin_password_hash',  password_hash($sec['admin_password'],  PASSWORD_DEFAULT)]);

            // Create default tenant from Graph credentials
            TenantService::ensureTable();
            $stmt = $pdo->prepare("
                INSERT INTO tenants (name, tenant_id, client_id, client_secret, is_default, is_active, notes, created_at)
                VALUES (?, ?, ?, ?, 1, 1, 'Default tenant (created during install)', NOW())
            ");
            $stmt->execute(['Default', $graph['tenant_id'], $graph['client_id'], $graph['client_secret']]);

            // Lock installer
            InstallService::lock();

            // Upgrade .htaccess from the minimal shipping version to the full
            // production version (security headers, caching, folder protection).
            // Non-fatal: install still succeeds if the file is read-only.
            if (!InstallService::writeHtaccess()) {
                Logger::warn('install.htaccess', 'Could not write production .htaccess; minimal shipping version still in place.');
            }

            // Clear session progress
            Session::forget(self::SESSION_KEY);

            Logger::info('install.completed', 'Installation finished successfully', [
                'app_name'      => $sec['app_name'],
                'graph_tenant'  => $graph['tenant_id'],
                'database'      => $db['name'] . '@' . $db['host'],
            ]);
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
            // Best-effort cleanup: leave config so user can retry
            Logger::error('install.failed', $e->getMessage());
        }

        if (!empty($errors)) {
            $this->step('complete', ['success' => false, 'errors' => $errors]);
            return;
        }
        $this->step('complete', [
            'success'   => true,
            'login_url' => App::instance()->baseUrl('/login'),
            'admin_url' => App::instance()->baseUrl('/admin/login'),
        ]);
    }

    public function reset(Request $req): void
    {
        // Allows clearing the install session if user gets stuck
        Session::forget(self::SESSION_KEY);
        Flash::info('Installation progress reset.');
        $this->redirect('/install');
    }

    // ---------- helpers ----------

    private function guardAlreadyInstalled(): void
    {
        if (InstallService::isLocked()) {
            $this->step('complete', [
                'success'   => true,
                'locked'    => true,
                'login_url' => App::instance()->baseUrl('/login'),
                'admin_url' => App::instance()->baseUrl('/admin/login'),
            ]);
            exit;
        }
    }

    private function step(string $view, array $data = []): void
    {
        $progress = (int)Session::get(self::SESSION_KEY . '_step', 0);
        $stepDef = [
            ['key' => 'welcome',      'label' => 'Welcome'],
            ['key' => 'requirements', 'label' => 'Requirements'],
            ['key' => 'database',     'label' => 'Database'],
            ['key' => 'graph',        'label' => 'Graph API'],
            ['key' => 'security',     'label' => 'Security'],
            ['key' => 'complete',     'label' => 'Complete'],
        ];
        $idx = array_search($view, array_column($stepDef, 'key'), true);
        $data['steps']   = $stepDef;
        $data['current'] = $idx === false ? 0 : $idx;
        $data['_flash']  = Flash::pull();
        \App\Core\View::render('install/' . $view, $data, 'layouts/install');
    }

    private function mark(int $step): void
    {
        $cur = (int)Session::get(self::SESSION_KEY . '_step', 0);
        if ($step > $cur) Session::set(self::SESSION_KEY . '_step', $step);
    }

    private function requireStep(int $step): void
    {
        $cur = (int)Session::get(self::SESSION_KEY . '_step', 0);
        if ($cur < $step) {
            Flash::warning('Please complete the previous steps first.');
            $this->redirect('/install');
        }
    }

    private function setProgress(string $key, array $data): void
    {
        $all = Session::get(self::SESSION_KEY, []);
        if (!is_array($all)) $all = [];
        $all[$key] = $data;
        Session::set(self::SESSION_KEY, $all);
    }

    private function getProgress(string $key, array $default): array
    {
        $all = Session::get(self::SESSION_KEY, []);
        if (!is_array($all)) return $default;
        return array_merge($default, (array)($all[$key] ?? []));
    }
}
