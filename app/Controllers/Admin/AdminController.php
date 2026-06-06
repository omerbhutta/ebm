<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\Settings;
use App\Services\MailboxService;
use App\Services\SuppressionService;
use App\Core\Logger;

/**
 * Admin home — overview cards + quick links.
 */
final class AdminController extends Controller
{
    public function index(Request $req): void
    {
        Auth::requireAdmin();

        $mailboxes = MailboxService::all(false);
        $active = 0;
        foreach ($mailboxes as $m) if ((int)$m['is_active'] === 1) $active++;

        $stats = SuppressionService::stats();
        $recentLogs = Logger::recent(10);

        $this->view('admin/index', [
            'mailboxes_total'  => count($mailboxes),
            'mailboxes_active' => $active,
            'mailboxes_paused' => count($mailboxes) - $active,
            'supp_total'       => (int)($stats['summary']['total'] ?? 0),
            'supp_bounces'     => (int)($stats['summary']['total_bounces'] ?? 0),
            'supp_last_sync'   => $stats['last_sync'] ?? null,
            'supp_last_7d'     => (int)($stats['summary']['last_7d'] ?? 0),
            'supp_last_24h'    => (int)($stats['summary']['last_24h'] ?? 0),
            'recent_logs'      => $recentLogs,
            'mailboxes'        => $mailboxes,
            'top_domains'      => $stats['top_domains'] ?? [],
        ]);
    }
}
