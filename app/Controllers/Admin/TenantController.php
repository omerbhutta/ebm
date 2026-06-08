<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\Flash;
use App\Services\TenantService;
use App\Services\MailboxService;

final class TenantController extends Controller
{
    public function index(Request $req): void
    {
        Auth::requireAdmin();
        $all = TenantService::all();
        $this->view('admin/tenants', ['tenants' => $all]);
    }

    public function add(Request $req): void
    {
        Auth::requireAdmin();
        $this->view('admin/tenant_form', ['tenant' => null]);
    }

    public function edit(Request $req, string $id): void
    {
        Auth::requireAdmin();
        $tenant = TenantService::find((int)$id);
        if (!$tenant) {
            Flash::error('Tenant not found.');
            $this->redirect('/admin/tenants');
        }
        $this->view('admin/tenant_form', ['tenant' => $tenant]);
    }

    public function store(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);

        $name   = trim((string)$req->post('name', ''));
        $tid    = trim((string)$req->post('tenant_id', ''));
        $cid    = trim((string)$req->post('client_id', ''));
        $secret = trim((string)$req->post('client_secret', ''));
        $notes  = trim((string)$req->post('notes', ''));

        $v = $this->validate(compact('name'), ['name' => 'required|max:255']);
        if (!$v->ok()) {
            Flash::error($v->first('name') ?: 'Validation failed.');
            $this->redirect('/admin/tenants/add');
        }

        $r = TenantService::add($name, $tid, $cid, $secret, $notes ?: null);
        if (!$r['ok']) {
            Flash::error($r['error']);
            $this->redirect('/admin/tenants/add');
        }
        Flash::success("Tenant <strong>" . htmlspecialchars($name) . "</strong> added.");
        $this->redirect('/admin/tenants');
    }

    public function update(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);

        $id     = (int)$req->post('id', 0);
        $name   = trim((string)$req->post('name', ''));
        $tid    = trim((string)$req->post('tenant_id', ''));
        $cid    = trim((string)$req->post('client_id', ''));
        $secret = trim((string)$req->post('client_secret', ''));
        $notes  = trim((string)$req->post('notes', ''));

        $tenant = TenantService::find($id);
        if (!$tenant) {
            Flash::error('Tenant not found.');
            $this->redirect('/admin/tenants');
        }

        $fields = [
            'name'      => $name,
            'tenant_id' => $tid,
            'client_id' => $cid,
            'notes'     => $notes ?: null,
        ];
        if ($secret !== '') {
            $fields['client_secret'] = $secret;
        }

        TenantService::update($id, $fields);
        Flash::success("Tenant <strong>" . htmlspecialchars($name) . "</strong> updated.");
        $this->redirect('/admin/tenants');
    }

    public function toggle(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);
        $id = (int)$req->post('id', 0);
        if (TenantService::toggle($id)) {
            $t = TenantService::find($id);
            Flash::success("Tenant <strong>" . htmlspecialchars($t['name']) . "</strong> " . ((int)$t['is_active'] === 1 ? 'enabled' : 'paused') . '.');
        } else {
            Flash::error('Tenant not found.');
        }
        $this->redirect('/admin/tenants');
    }

    public function destroy(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);
        $id = (int)$req->post('id', 0);
        $t = TenantService::find($id);
        if ($t && TenantService::remove($id)) {
            Flash::success("Tenant <strong>" . htmlspecialchars($t['name']) . "</strong> removed. Mailboxes unassigned.");
        } else {
            Flash::error($t && (int)$t['is_default'] === 1 ? 'Cannot delete the default tenant.' : 'Tenant not found.');
        }
        $this->redirect('/admin/tenants');
    }

    public function setDefault(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);
        $id = (int)$req->post('id', 0);
        if (TenantService::setDefault($id)) {
            $t = TenantService::find($id);
            Flash::success("Tenant <strong>" . htmlspecialchars($t['name']) . "</strong> set as default.");
        } else {
            Flash::error('Could not set default tenant.');
        }
        $this->redirect('/admin/tenants');
    }
}
