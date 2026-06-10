<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Logger;
use App\Services\SuppressionService;
use App\Services\BounceService;

/**
 * Public-ish API endpoint used by external sending systems (e.g. LIS).
 * Authenticated by shared API key — fails closed if missing.
 */
final class ApiController extends Controller
{
    public function check(Request $req): void
    {
        $providedKey = $req->header('X-Api-Key')
            ?: ($req->input('key', '') ?: '');

        $expected = (string)Settings::get('check_api_key', '');
        if ($expected === '' || $expected === 'change-me') {
            Response::json(['error' => 'API key not configured on server.'], 503);
        }
        if (!is_string($providedKey) || $providedKey === '' || !hash_equals($expected, $providedKey)) {
            Logger::warn('api.check.bad_key', 'Bad API key', ['ip' => $req->ip()]);
            Response::json(['error' => 'Invalid or missing API key.'], 401);
        }

        // Gather emails from GET (?email=) or POST (json or form)
        $emails = [];
        if ($req->isGet()) {
            $single = trim((string)$req->query('email', ''));
            if ($single !== '') $emails = [$single];
        } else {
            $raw = $req->input('emails', []);
            if (is_string($raw)) {
                if (strpos($raw, '[') === 0) {
                    $j = json_decode($raw, true);
                    if (is_array($j)) $emails = $j;
                } else {
                    $emails = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
                }
            } elseif (is_array($raw)) {
                $emails = $raw;
            }
        }

        if (empty($emails)) {
            Response::json([
                'error' => 'No valid email(s) provided.',
                'hint'  => 'GET ?email=user@example.com or POST {"emails":["a@b.com"]} with X-Api-Key header.',
            ], 400);
        }

        try {
            $suppressed = SuppressionService::check($emails);
            $cleaned = array_values(array_unique(array_filter(array_map(
                fn($e) => strtolower(trim((string)$e)),
                $emails
            ), fn($e) => $e !== '' && strpos($e, '@') !== false)));

            $results = [];
            $blocked = 0;
            foreach ($cleaned as $e) {
                if (isset($suppressed[$e])) {
                    $results[] = [
                        'email'        => $e,
                        'suppressed'   => true,
                        'bounce_count' => $suppressed[$e]['bounce_count'],
                        'last_seen'    => $suppressed[$e]['last_seen'],
                    ];
                    $blocked++;
                } else {
                    $results[] = ['email' => $e, 'suppressed' => false];
                }
            }

            Response::json([
                'checked' => count($results),
                'blocked' => $blocked,
                'allowed' => count($results) - $blocked,
                'results' => $results,
            ]);
        } catch (\Throwable $e) {
            Logger::error('api.check.db_error', $e->getMessage());
            // Fail open with logged error
            Response::json([
                'error'   => 'Suppression lookup failed; failing open.',
                'checked' => count($emails),
                'blocked' => 0,
                'allowed' => count($emails),
                'results' => array_map(fn($e) => ['email' => $e, 'suppressed' => false], $emails),
            ], 200);
        }
    }

    /**
     * GET /api/suppression — return the full suppression list (paginated).
     * Auth: X-Api-Key header or ?key= query param.
     */
    public function list(Request $req): void
    {
        $providedKey = $req->header('X-Api-Key')
            ?: ($req->input('key', '') ?: '');

        $expected = (string)Settings::get('check_api_key', '');
        if ($expected === '' || $expected === 'change-me') {
            Response::json(['error' => 'API key not configured on server.'], 503);
        }
        if (!is_string($providedKey) || $providedKey === '' || !hash_equals($expected, $providedKey)) {
            Logger::warn('api.suppression.bad_key', 'Bad API key on suppression list', ['ip' => $req->ip()]);
            Response::json(['error' => 'Invalid or missing API key.'], 401);
        }

        $page    = max(1, (int)$req->query('page', 1));
        $perPage = max(10, min(500, (int)$req->query('per_page', 100)));
        $search  = trim((string)$req->query('search', ''));

        $pdo = \App\Core\Database::connection();

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = "WHERE email LIKE ?";
            $params[] = '%' . $search . '%';
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM suppression_list $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $rows = [];
        if ($total > 0) {
            $stmt = $pdo->prepare("
                SELECT email, first_seen, last_seen, bounce_count
                FROM suppression_list
                $where
                ORDER BY last_seen DESC
                LIMIT $perPage OFFSET $offset
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) {
                $r['first_seen'] = $r['first_seen'] ?? null;
                $r['last_seen']  = $r['last_seen'] ?? null;
                $r['bounce_count'] = (int)$r['bounce_count'];
            }
        }

        Response::json([
            'ok'       => true,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => max(1, (int)ceil($total / $perPage)),
            'data'     => $rows,
        ]);
    }
}
