<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\Flash;
use App\Services\SuppressionService;
use App\Services\ExportService;

/**
 * Suppression list — list, add manual, remove, clear, reset counts, export.
 */
final class SuppressionController extends Controller
{
    public function index(Request $req): void
    {
        Auth::requireViewer();

        $search = trim((string)$req->query('q', ''));
        $page   = max(1, (int)$req->query('page', 1));
        $per    = max(10, min(200, (int)$req->query('per', 50)));

        $data = SuppressionService::list($search, $page, $per);
        $stats = SuppressionService::stats();
        $domains = SuppressionService::domains();

        // Export
        $export = (string)$req->query('export', '');
        if ($export === 'csv' || $export === 'excel') {
            // Fetch ALL matching (no pagination) for export
            $all = SuppressionService::list($search, 1, 100000);
            $rows = array_map(fn($r) => [
                'email'        => $r['email'],
                'bounce_count' => $r['bounce_count'],
                'first_seen'   => $r['first_seen'],
                'last_seen'    => $r['last_seen'],
            ], $all['rows']);
            $headers = ['Email','Bounce_Count','First_Seen','Last_Seen'];
            $stamp = date('Ymd-His');
            if ($export === 'csv') ExportService::csv($rows, $headers, "suppression-{$stamp}.csv");
            ExportService::excel($rows, $headers, "suppression-{$stamp}.xls");
        }

        $this->view('suppression/index', [
            'rows'        => $data['rows'],
            'total'       => $data['total'],
            'page'        => $data['page'],
            'pages'       => $data['pages'],
            'per'         => $data['per'],
            'q'           => $search,
            'stats'       => $stats,
            'all_domains' => $domains,
        ]);
    }

    public function add(Request $req): void
    {
        Auth::requireViewer();
        $this->verifyCsrf($req);

        $email = (string)$req->post('email', '');
        $r = SuppressionService::add($email);
        if (!$r['ok']) {
            Flash::error($r['error']);
        } else {
            Flash::success($r['new'] ? "Added {$r['email']} to suppression list." : "{$r['email']} was already on the list — counter incremented.");
        }
        $this->redirect('/suppression');
    }

    public function remove(Request $req): void
    {
        Auth::requireViewer();
        $this->verifyCsrf($req);
        $id = (int)$req->post('id', 0);
        if (SuppressionService::remove($id)) {
            Flash::success('Removed from suppression list.');
        } else {
            Flash::error('Could not remove — entry not found.');
        }
        $this->redirect('/suppression');
    }

    public function clear(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);
        $n = SuppressionService::clearAll();
        Flash::success("Cleared {$n} entries from suppression list and NDR tracking.");
        $this->redirect('/suppression');
    }

    public function resetCounts(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);
        $n = SuppressionService::resetCounts();
        Flash::success($n > 0
            ? "Reset bounce counts on {$n} entries."
            : 'All bounce counts already at 1.');
        $this->redirect('/suppression');
    }

    public function purgeNdrs(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);
        $n = SuppressionService::purgeProcessedNdrs();
        Flash::success($n > 0
            ? "Cleared {$n} NDR tracking records. The next dashboard refresh will re-process all NDRs."
            : 'No NDR tracking records to clear.');
        $this->redirect('/suppression');
    }
}
