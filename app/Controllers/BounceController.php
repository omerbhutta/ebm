<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Auth;
use App\Core\Settings;
use App\Core\Cache;
use App\Core\Response;
use App\Services\MailboxService;
use App\Services\GraphService;
use App\Services\BounceService;
use App\Services\ExportService;

/**
 * Bounce records — list view, single-message details (modal AJAX), export.
 */
final class BounceController extends Controller
{
    public function index(Request $req): void
    {
        Auth::requireViewer();

        $folders = json_decode((string)Settings::get('monitor_folders', '{}'), true) ?: [];
        if (empty($folders)) $folders = ['Inbox' => 'inbox', 'Junk Email' => 'junkemail', 'Deleted Items' => 'deleteditems'];

        $cacheTtl = Settings::int('cache_ttl', 300);
        $forceRefresh = $req->query('refresh') === '1';

        $rows = [];
        $errors = [];
        foreach (MailboxService::all(true) as $mb) {
            foreach ($folders as $label => $folderId) {
                $cacheKey = MailboxService::slug($mb['email']) . '__' . preg_replace('/[^a-z0-9]/i','',$label);
                $messages = $forceRefresh ? null : Cache::get($cacheKey);
                if (!is_array($messages)) {
                    $r = GraphService::fetchUndeliverableMessages($mb['email'], [$label => $folderId]);
                    if (!$r['ok']) {
                        $errors[] = "{$mb['email']} / {$label}: {$r['error']}";
                        continue;
                    }
                    $messages = $r['messages'];
                    Cache::put($cacheKey, $messages, $cacheTtl);
                }
                foreach ($messages as $m) {
                    $failed = BounceService::extractFailedRecipients($m, $mb['email']);
                    $rows[$m['id']] = [
                        'id'         => $m['id'],
                        'subject'    => $m['subject'] ?? '(no subject)',
                        'folder'     => $label,
                        'date'       => $m['receivedDateTime'] ?? '',
                        'date_human' => BounceService::formatDate($m['receivedDateTime'] ?? null),
                        'from'       => $m['from']['emailAddress']['address'] ?? '',
                        'failed'     => $failed,
                        'failed_str' => implode(', ', $failed),
                        'mailbox'    => $mb['email'],
                    ];
                }
            }
        }
        $rows = array_values($rows);
        usort($rows, fn($a, $b) => strcmp((string)$b['date'], (string)$a['date']));

        // Filters
        $q       = trim((string)$req->query('q', ''));
        $folder  = trim((string)$req->query('folder', ''));
        $mailbox = trim((string)$req->query('mailbox', ''));
        $sort    = (string)$req->query('sort', 'date_desc');

        $filtered = array_filter($rows, function ($r) use ($q, $folder, $mailbox) {
            if ($folder !== '' && $r['folder'] !== $folder) return false;
            if ($mailbox !== '' && strcasecmp($r['mailbox'], $mailbox) !== 0) return false;
            if ($q !== '') {
                $blob = strtolower($r['subject'] . ' ' . $r['from'] . ' ' . $r['mailbox'] . ' ' . $r['failed_str']);
                if (strpos($blob, strtolower($q)) === false) return false;
            }
            return true;
        });
        $filtered = array_values($filtered);

        usort($filtered, function ($a, $b) use ($sort) {
            switch ($sort) {
                case 'date_asc':    return strcmp((string)$a['date'], (string)$b['date']);
                case 'subject_asc': return strcasecmp($a['subject'], $b['subject']);
                case 'subject_desc':return strcasecmp($b['subject'], $a['subject']);
                case 'mailbox_asc': return strcasecmp($a['mailbox'], $b['mailbox']);
                default:            return strcmp((string)$b['date'], (string)$a['date']);
            }
        });

        // Export
        $export = (string)$req->query('export', '');
        if ($export === 'csv' || $export === 'excel') {
            $exportRows = array_map(fn($r) => [
                'date'      => $r['date_human'],
                'mailbox'   => $r['mailbox'],
                'folder'    => $r['folder'],
                'subject'   => $r['subject'],
                'from'      => $r['from'],
                'failed_recipients' => $r['failed_str'],
            ], $filtered);
            $headers = ['Date','Mailbox','Folder','Subject','From','Failed_Recipients'];
            $stamp = date('Ymd-His');
            if ($export === 'csv') ExportService::csv($exportRows, $headers, "bounce-records-{$stamp}.csv");
            ExportService::excel($exportRows, $headers, "bounce-records-{$stamp}.xls");
        }

        // Pagination
        $perPage = max(10, min(200, (int)$req->query('per', 25)));
        $page = max(1, (int)$req->query('page', 1));
        $total = count($filtered);
        $pages = max(1, (int)ceil($total / $perPage));
        $page  = min($page, $pages);
        $sliced = array_slice($filtered, ($page - 1) * $perPage, $perPage);

        $this->view('bounces/index', [
            'rows'      => $sliced,
            'total'     => $total,
            'page'      => $page,
            'pages'     => $pages,
            'per_page'  => $perPage,
            'q'         => $q,
            'folder'    => $folder,
            'mailbox'   => $mailbox,
            'sort'      => $sort,
            'folders'   => array_keys($folders),
            'mailboxes' => MailboxService::all(true),
            'errors'    => $errors,
        ]);
    }

    public function details(Request $req): void
    {
        Auth::requireViewer();

        $id      = (string)$req->query('id', '');
        $folder  = (string)$req->query('folder', '');
        $mailbox = (string)$req->query('mailbox', '');
        if ($id === '' || $mailbox === '') {
            Response::json(['error' => 'Missing id or mailbox'], 400);
        }

        $known = MailboxService::findByEmail($mailbox);
        if (!$known) {
            Response::json(['error' => 'Unknown mailbox'], 403);
        }

        $cacheKey = 'body_' . md5($mailbox . '|' . $id);
        $cached = Cache::get($cacheKey);
        $cacheTtl = Settings::int('cache_ttl', 300);

        if (is_array($cached)) {
            $cached['cached'] = true;
            Response::json($cached);
        }

        $r = GraphService::fetchMessage($mailbox, $id);
        if (!$r['ok']) {
            Response::json(['error' => $r['error']], 500);
        }
        $msg = $r['message'];
        $failed = BounceService::extractFailedRecipients($msg, $mailbox);
        $reason = BounceService::extractBounceReason($msg);

        $to = [];
        foreach (($msg['toRecipients'] ?? []) as $rcpt) {
            if (!empty($rcpt['emailAddress']['address'])) $to[] = $rcpt['emailAddress']['address'];
        }
        $from = trim(
            ($msg['from']['emailAddress']['name'] ?? '') . ' <' .
            ($msg['from']['emailAddress']['address'] ?? '') . '>'
        );
        $from = preg_replace('/^<\s*>$/', '', $from);

        $payload = [
            'subject'   => $msg['subject'] ?? '',
            'from'      => $from ?: '—',
            'to'        => implode(', ', $to) ?: '—',
            'received'  => $msg['receivedDateTime'] ?? '',
            'received_human' => BounceService::formatDate($msg['receivedDateTime'] ?? null),
            'folder'    => $folder ?: '—',
            'mailbox'   => $mailbox,
            'bodyType'  => $msg['body']['contentType'] ?? 'html',
            'body'      => $msg['body']['content'] ?? '',
            'failed'    => $failed,
            'reason'    => $reason,
            'cached'    => false,
        ];
        Cache::put($cacheKey, $payload, $cacheTtl);
        Response::json($payload);
    }
}
