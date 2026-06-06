<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\Logger;

/**
 * Activity & login logs.
 */
final class LogController extends Controller
{
    public function index(Request $req): void
    {
        Auth::requireAdmin();
        $level = (string)$req->query('level', '');
        $limit = max(10, min(1000, (int)$req->query('limit', 200)));
        $logs = Logger::recent($limit, $level !== '' ? $level : null);
        $this->view('admin/logs', [
            'logs' => $logs,
            'level' => $level,
            'limit' => $limit,
        ]);
    }

    public function prune(Request $req): void
    {
        Auth::requireAdmin();
        $this->verifyCsrf($req);
        $days = max(1, (int)$req->post('days', 30));
        $n = Logger::prune($days);
        Flash::success("Pruned {$n} log entries older than {$days} days.");
        $this->redirect('/admin/logs');
    }
}
