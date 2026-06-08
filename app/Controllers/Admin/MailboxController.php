<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\Flash;
use App\Services\MailboxService;
use App\Services\GraphService;
use App\Services\TenantService;

/**
 * Manage monitored mailboxes.
 */
final class MailboxController extends Controller
{
    public function index(Request $req): void
    {
        Auth::requireAdmin();
        $all = MailboxService::all(false);
        $per    = max(5, min(200, (int)$req->query('per', 25)));
        $page   = max(1, (int)$req->query('page', 1));
        $total  = count($all);
        $pages  = max(1, (int)ceil($total / $per));
        $page   = min($page, $pages);
        $offset = ($page - 1) * $per;
        $rows   = array_slice($all, $offset, $per);

        $tenants = \App\Services\TenantService::all(true);

        $this->view('admin/mailboxes', [
            'mailboxes' => $rows,
            'total'     => $total,
            'page'      => $page,
            'pages'     => $pages,
            'per'       => $per,
            'tenants'   => $tenants,
        ]);
    }

    public function store(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);

        $email    = (string)$req->post('email', '');
        $desc     = (string)$req->post('description', '');
        $tenantId = (int)$req->post('tenant_id', 0);
        $v = $this->validate(compact('email','desc'), [
            'email' => 'required|email',
            'desc'  => 'max:255',
        ]);
        if (!$v->ok()) {
            Flash::error($v->first('email') ?: $v->first('desc') ?: 'Validation failed.');
            $this->redirect('/admin/mailboxes');
        }
        $r = MailboxService::add($email, $desc ?: null, $tenantId);
        if (!$r['ok']) {
            Flash::error($r['error']);
        } else {
            Flash::success("Mailbox <strong>" . htmlspecialchars($email) . "</strong> added.");
        }
        $this->redirect('/admin/mailboxes');
    }

    public function toggle(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);
        $id = (int)$req->post('id', 0);
        if (MailboxService::toggle($id)) {
            $mb = MailboxService::find($id);
            Flash::success("Mailbox <strong>" . htmlspecialchars($mb['email']) . "</strong> " . ((int)$mb['is_active'] === 1 ? 'enabled' : 'paused') . '.');
        } else {
            Flash::error('Mailbox not found.');
        }
        $this->redirect('/admin/mailboxes');
    }

    public function update(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);
        $id = (int)$req->post('id', 0);
        $desc = trim((string)$req->post('description', ''));
        if (MailboxService::update($id, ['description' => $desc ?: null])) {
            Flash::success('Description updated.');
        } else {
            Flash::error('Could not update mailbox.');
        }
        $this->redirect('/admin/mailboxes');
    }

    public function destroy(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);
        $id = (int)$req->post('id', 0);
        $mb = MailboxService::find($id);
        if ($mb && MailboxService::remove($id)) {
            Flash::success("Mailbox <strong>" . htmlspecialchars($mb['email']) . "</strong> removed.");
        } else {
            Flash::error('Mailbox not found.');
        }
        $this->redirect('/admin/mailboxes');
    }

    public function test(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);
        $id = (int)$req->post('id', 0);
        $mb = MailboxService::find($id);
        if (!$mb) {
            Flash::error('Mailbox not found.');
            $this->redirect('/admin/mailboxes');
        }
        // Resolve tenant-specific token
        $token = null;
        $tenantId = (int)($mb['tenant_id'] ?? 0);
        if ($tenantId > 0) {
            $tenant = TenantService::find($tenantId);
            if ($tenant) {
                $token = GraphService::getToken((string)$tenant['tenant_id'], (string)$tenant['client_id'], (string)$tenant['client_secret']);
            }
        }
        if (!$token) {
            $default = TenantService::getDefault();
            if ($default && (int)$default['is_active']) {
                $token = GraphService::getToken((string)$default['tenant_id'], (string)$default['client_id'], (string)$default['client_secret']);
            }
        }
        if (!$token) {
            $token = GraphService::getToken();
        }
        $r = GraphService::testMailboxAccess($mb['email'], $token);
        if ($r['ok']) {
            Flash::success("Graph connection to <strong>" . htmlspecialchars($mb['email']) . "</strong> succeeded.");
            MailboxService::recordSync($mb['email'], null, $tenantId);
        } else {
            Flash::error("Connection failed: " . htmlspecialchars($r['error']));
            MailboxService::recordSync($mb['email'], $r['error'], $tenantId);
        }
        $this->redirect('/admin/mailboxes');
    }

    public function clearCache(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);
        $id = (int)$req->post('id', 0);
        $mb = MailboxService::find($id);
        if (!$mb) {
            Flash::error('Mailbox not found.');
            $this->redirect('/admin/mailboxes');
        }
        MailboxService::invalidateCache($mb['email']);
        Flash::success("Cache cleared for <strong>" . htmlspecialchars($mb['email']) . "</strong>.");
        $this->redirect('/admin/mailboxes');
    }
}
