<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\Settings;
use App\Core\Flash;
use App\Core\Cache;
use App\Services\GraphService;

/**
 * Microsoft Graph API settings management.
 */
final class GraphController extends Controller
{
    public function index(Request $req): void
    {
        Auth::requireAdmin();
        $this->view('admin/graph', [
            'tenant_id'     => (string)Settings::get('graph_tenant_id', ''),
            'client_id'     => (string)Settings::get('graph_client_id', ''),
            'client_secret' => (string)Settings::get('graph_client_secret', ''),
        ]);
    }

    public function update(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);

        $tenantId     = trim((string)$req->post('tenant_id',''));
        $clientId     = trim((string)$req->post('client_id',''));
        $clientSecret = trim((string)$req->post('client_secret',''));
        $action       = (string)$req->post('action', 'save');

        $v = $this->validate(compact('tenantId','clientId','clientSecret'), [
            'tenantId'     => 'required|uuid',
            'clientId'     => 'required|uuid',
            'clientSecret' => 'required|min:8',
        ]);
        if (!$v->ok()) {
            foreach ($v->errors() as $msgs) foreach ($msgs as $m) Flash::error($m);
            $this->redirect('/admin/graph');
        }

        if ($action === 'test') {
            $r = GraphService::testCredentials($tenantId, $clientId, $clientSecret);
            if ($r['ok']) {
                Flash::success('Graph API test succeeded — credentials are valid.');
            } else {
                Flash::error('Graph API test failed: ' . $r['error']);
            }
            $this->redirect('/admin/graph');
        }

        Settings::setMany([
            'graph_tenant_id'     => $tenantId,
            'graph_client_id'     => $clientId,
            'graph_client_secret' => $clientSecret,
        ]);
        Cache::forgetPattern('graph_token_*.cache');
        Flash::success('Graph API credentials updated.');
        $this->redirect('/admin/graph');
    }
}
