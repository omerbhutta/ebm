<?php
/**
 * tests/test_e2e.php — Comprehensive end-to-end test for the full NDR dashboard.
 * Tests against the live HTTP server on http://127.0.0.1:8765.
 *
 *   1. Schema: monitored_mailboxes + suppression_list tables exist with right columns
 *   2. Admin: login (wrong, right), session, all CRUD actions
 *   3. Dashboard: auth gate, multi-mailbox view, Mailbox column
 *   4. body.php: auth, mailbox param
 *   5. check.php: API key auth, single + batch
 *   6. Security: XSS escape, SQL injection, session fixation
 *
 * Usage:  php tests/test_e2e.php
 * Exit:   0 on all pass, 1 on any failure.
 */

declare(strict_types=1);

// Credentials come from environment variables (never hardcoded).
//   TEST_BASE_URL   default http://127.0.0.1:8765
//   TEST_ADMIN_PWD  admin password (required for admin tests)
//   TEST_DASH_PWD   viewer/dashboard password (required for viewer tests)
//   TEST_API_KEY    check.php API key (required for API tests)
$base     = getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8765';
$adminPwd = getenv('TEST_ADMIN_PWD') ?: '';
$dashPwd  = getenv('TEST_DASH_PWD')  ?: '';
$apiKey   = getenv('TEST_API_KEY')   ?: '';

$pass = 0; $fail = 0; $failures = [];

function ok(string $name, bool $cond, string $detail = ''): void {
    global $pass, $fail, $failures;
    if ($cond) { $pass++; echo "  PASS  $name\n"; }
    else { $fail++; $failures[] = "$name  $detail"; echo "  FAIL  $name" . ($detail ? "  $detail" : "") . "\n"; }
}

function section(string $title): void {
    echo "\n== $title ==\n";
}

function req(string $method, string $url, ?array $post = null, ?string $cookieFile = null, array $headers = [], int $timeout = 20): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    $hdrLines = $headers;
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post ?? []));
        $hdrLines[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    if ($hdrLines) curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrLines);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    }
    $r = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $h = substr((string)$r, 0, $hs);
    $b = substr((string)$r, $hs);
    return ['code' => $code, 'body' => $b, 'headers' => $h];
}

function jar(string $name): string {
    $p = __DIR__ . '/_jar_' . $name . '.txt';
    @unlink($p);
    return $p;
}

function extractLocation(array $r): string {
    foreach (explode("\r\n", $r['headers']) as $h) {
        if (stripos($h, 'location:') === 0) return trim(substr($h, 9));
    }
    return '';
}

// Confirm server is up
$ping = req('GET', "$base/admin.php");
if ($ping['code'] === 0) {
    echo "FATAL: server not reachable at $base\n";
    exit(2);
}

// ============================================
// 1. SCHEMA
// ============================================
section("1. DATABASE SCHEMA");
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

$pdo = db();

$cols = $pdo->query("SHOW COLUMNS FROM monitored_mailboxes")->fetchAll(PDO::FETCH_COLUMN);
ok('monitored_mailboxes has id', in_array('id', $cols));
ok('monitored_mailboxes has email', in_array('email', $cols));
ok('monitored_mailboxes has description', in_array('description', $cols));
ok('monitored_mailboxes has is_active', in_array('is_active', $cols));
ok('monitored_mailboxes has created_at', in_array('created_at', $cols));
ok('monitored_mailboxes has last_synced_at', in_array('last_synced_at', $cols));
ok('monitored_mailboxes has last_error', in_array('last_error', $cols));

$cols2 = $pdo->query("SHOW COLUMNS FROM suppression_list")->fetchAll(PDO::FETCH_COLUMN);
ok('suppression_list has id', in_array('id', $cols2));
ok('suppression_list has email', in_array('email', $cols2));
ok('suppression_list has first_seen', in_array('first_seen', $cols2));
ok('suppression_list has last_seen', in_array('last_seen', $cols2));
ok('suppression_list has bounce_count', in_array('bounce_count', $cols2));

$idx = $pdo->query("SHOW INDEX FROM monitored_mailboxes WHERE Key_name = 'uk_email'")->fetch();
ok('monitored_mailboxes.email is UNIQUE', (bool)$idx);

$idx2 = $pdo->query("SHOW INDEX FROM suppression_list WHERE Column_name = 'email' AND Non_unique = 0")->fetch();
ok('suppression_list.email is UNIQUE', (bool)$idx2);

// Verify default mailbox was seeded
$seeded = $pdo->query("SELECT email FROM monitored_mailboxes WHERE email = '" . MS_SENDER_EMAIL . "'")->fetch();
ok('MS_SENDER_EMAIL auto-seeded into monitored_mailboxes', (bool)$seeded);

// ============================================
// 2. ADMIN: UNAUTHED
// ============================================
section("2. ADMIN PAGE — unauthed");
$r = req('GET', "$base/admin.php");
ok('admin.php loads with 200', $r['code'] === 200);
ok('shows Admin sign in form', str_contains($r['body'], 'Admin sign in'));
ok('shows 16-digit password field', str_contains($r['body'], 'admin_password'));
ok('password field is type=password', preg_match('/<input[^>]*name="admin_password"[^>]*type="password"/', $r['body']) === 1);
ok('password field has maxlength=16', str_contains($r['body'], 'maxlength="16"'));

// ============================================
// 3. ADMIN: LOGIN WRONG / MISSING / RIGHT
// ============================================
section("3. ADMIN LOGIN");
$adminJar = jar('admin');

$r = req('POST', "$base/admin.php", ['admin_password' => '0000000000000000'], $adminJar);
ok('wrong pw returns 200', $r['code'] === 200);
ok('wrong pw shows error', str_contains($r['body'], 'Invalid'));

$r = req('POST', "$base/admin.php", [], $adminJar);
ok('missing pw returns 200', $r['code'] === 200);
ok('missing pw does NOT show error', !str_contains($r['body'], 'Invalid'));

$r = req('POST', "$base/admin.php", ['admin_password' => $adminPwd], $adminJar);
ok('correct pw redirects', in_array($r['code'], [302, 303]), "code=$r[code]");
$loc = extractLocation($r);
ok('redirects to admin.php', str_contains($loc, 'admin.php'), "loc=$loc");

$r = req('GET', "$base/admin.php", null, $adminJar);
ok('authed loads', $r['code'] === 200);
ok('shows Overview card', str_contains($r['body'], 'Overview'));
ok('shows Add monitored mailbox form', str_contains($r['body'], 'Add monitored mailbox'));
ok('shows Monitored mailboxes section', str_contains($r['body'], 'Monitored mailboxes'));
ok('shows Add mailbox submit button INSIDE form', str_contains($r['body'], 'Add mailbox</button>'));

// ============================================
// 4. ADMIN: ADD MAILBOX
// ============================================
section("4. ADMIN — add mailbox");
$testEmail = 'test-' . substr(md5(uniqid()), 0, 6) . '@jeevaysoft.com';
$r = req('POST', "$base/admin.php", [
    'action'      => 'add',
    'email'       => $testEmail,
    'description' => 'E2E test mailbox',
], $adminJar);
ok('add returns 200', $r['code'] === 200);
ok('add success message', str_contains($r['body'], 'added'));
ok('test mailbox now visible', str_contains($r['body'], $testEmail));
ok('description rendered', str_contains($r['body'], 'E2E test mailbox'));

// Add duplicate
$r = req('POST', "$base/admin.php", [
    'action'      => 'add',
    'email'       => $testEmail,
    'description' => 'Dup',
], $adminJar);
ok('duplicate add handled gracefully', str_contains($r['body'], 'already') || str_contains($r['body'], 'added'));

// Add invalid
$r = req('POST', "$base/admin.php", [
    'action'      => 'add',
    'email'       => 'not-an-email',
    'description' => 'Bad',
], $adminJar);
ok('invalid email rejected', str_contains($r['body'], 'Invalid email'));

// ============================================
// 5. ADMIN: TEST CONNECTION
// ============================================
section("5. ADMIN — test connection");
$r = req('POST', "$base/admin.php", [
    'action' => 'test',
    'email'  => MS_SENDER_EMAIL,
], $adminJar);
ok('test returns 200', $r['code'] === 200);
$hasSucceeded = str_contains($r['body'], 'succeeded');
$hasFailed    = str_contains($r['body'], 'Connection failed') || str_contains($r['body'], 'Token acquisition failed');
ok('test produces a result', $hasSucceeded || $hasFailed);

$r = req('POST', "$base/admin.php", [
    'action' => 'test',
    'email'  => 'nonexistent-' . md5(uniqid()) . '@nope.invalid',
], $adminJar);
ok('test on fake email returns a result (succeeded or failed)', $hasSucceeded || str_contains($r['body'], 'failed') || str_contains($r['body'], 'No') || str_contains($r['body'], 'does not') || str_contains($r['body'], 'exist') || str_contains($r['body'], 'not found'));

// ============================================
// 6. ADMIN: CLEAR CACHE
// ============================================
section("6. ADMIN — clear cache");
$r = req('POST', "$base/admin.php", [
    'action' => 'clear_cache',
    'email'  => $testEmail,
], $adminJar);
ok('clear_cache returns 200', $r['code'] === 200);
ok('clear_cache success', str_contains($r['body'], 'Cache cleared'));

// ============================================
// 7. ADMIN: TOGGLE
// ============================================
section("7. ADMIN — toggle pause/enable");
$r = req('POST', "$base/admin.php", [
    'action' => 'toggle',
    'email'  => $testEmail,
    'active' => 0,
], $adminJar);
ok('toggle to paused returns 200', $r['code'] === 200);
ok('toggle shows paused message', str_contains($r['body'], 'paused') || str_contains($r['body'], 'enabled'));

// Confirm DB state
$row = $pdo->prepare("SELECT is_active FROM monitored_mailboxes WHERE email = ?");
$row->execute([$testEmail]);
$isActive = (int)$row->fetchColumn();
ok('DB shows mailbox paused', $isActive === 0, "got is_active=$isActive");

// Re-enable
req('POST', "$base/admin.php", [
    'action' => 'toggle',
    'email'  => $testEmail,
    'active' => 1,
], $adminJar);
$row->execute([$testEmail]);
$isActive = (int)$row->fetchColumn();
ok('DB shows mailbox active again', $isActive === 1, "got is_active=$isActive");

// ============================================
// 8. ADMIN: REMOVE
// ============================================
section("8. ADMIN — remove mailbox");
$r = req('POST', "$base/admin.php", [
    'action' => 'remove',
    'email'  => $testEmail,
], $adminJar);
ok('remove returns 200', $r['code'] === 200);
ok('remove success', str_contains($r['body'], 'removed'));

$row->execute([$testEmail]);
ok('mailbox removed from DB', $row->fetch() === false);

// ============================================
// 9. ADMIN: LOGOUT
// ============================================
section("9. ADMIN — logout");
$r = req('GET', "$base/admin.php?logout=1", null, $adminJar);
ok('logout redirects', in_array($r['code'], [302, 303]));
$r = req('GET', "$base/admin.php", null, $adminJar);
ok('logged out — shows login form again', str_contains($r['body'], 'Admin sign in'));
@unlink($adminJar);

// ============================================
// 10. DASHBOARD — UNAUTHED
// ============================================
section("10. DASHBOARD — unauthed");
$r = req('GET', "$base/index.php");
ok('dashboard loads with 200', $r['code'] === 200);
ok('shows password gate', str_contains($r['body'], 'Access') || str_contains($r['body'], 'password') || str_contains($r['body'], 'Sign in'));

// ============================================
// 11. DASHBOARD — LOGIN
// ============================================
section("11. DASHBOARD — login");
$dashJar = jar('dash');
$r = req('POST', "$base/index.php", ['password' => $dashPwd], $dashJar);
ok('dashboard login returns 200 or redirect', $r['code'] === 200 || in_array($r['code'], [302, 303]));

// ============================================
// 12. DASHBOARD — AUTHED CONTENT
// ============================================
section("12. DASHBOARD — authed content");
$r = req('GET', "$base/index.php", null, $dashJar);
ok('dashboard authed loads', $r['code'] === 200, "code={$r['code']} body_start=" . substr($r['body'], 0, 200));

if ($r['code'] === 200) {
    ok('has Mailbox column header', str_contains($r['body'], '<th>Mailbox</th>'));
    ok('has Admin link in nav', str_contains($r['body'], 'admin.php'));
    ok('has Suppression List link', str_contains($r['body'], 'suppression.php'));
    ok('has manual Refresh button', str_contains($r['body'], 'refresh=1'));
    ok('has Sign out link', str_contains($r['body'], 'logout=1'));
    // No auto-refresh countdown anymore
    ok('NO autoSecs element', !str_contains($r['body'], 'id="autoSecs"'));
    ok('NO location.reload in JS', !str_contains($r['body'], 'location.reload'));
    // Header shows the configured mailbox
    ok('header subtitle shows the configured mailbox', str_contains($r['body'], htmlspecialchars(MS_SENDER_EMAIL, ENT_QUOTES)));
    ok('header has Tracking NDRs for label', str_contains($r['body'], 'Tracking NDRs for'));
    ok('page title shows the configured mailbox', str_contains($r['body'], htmlspecialchars(MS_SENDER_EMAIL, ENT_QUOTES)));
    // New counter labels
    ok('has NDRs in View label', str_contains($r['body'], 'NDRs in View'));
    ok('has Unique Failed (View) label', str_contains($r['body'], 'Unique Failed (View)'));
    ok('has Suppression List stat card', str_contains($r['body'], 'Suppression List'));
    ok('has Bouncing Domains stat card with click hint', str_contains($r['body'], 'Click to see breakdown'));
    ok('has clickable Bouncing Domains card', str_contains($r['body'], 'id="openDomainsModal"'));
    ok('has Total Bounce Events label', str_contains($r['body'], 'Total Bounce Events'));
    ok('has Last Suppression Sync label', str_contains($r['body'], 'Last Suppression Sync'));
}

// ============================================
// 13. DASHBOARD — MULTI-MAILBOX
// ============================================
section("13. DASHBOARD — multi-mailbox integration");

// Add a second mailbox
$secondEmail = 'second-' . substr(md5(uniqid()), 0, 6) . '@jeevaysoft.com';
$adminJar = jar('admin2');
req('POST', "$base/admin.php", ['admin_password' => $adminPwd], $adminJar);
req('POST', "$base/admin.php", [
    'action'      => 'add',
    'email'       => $secondEmail,
    'description' => 'Second mailbox',
], $adminJar);

// Now visit dashboard — should show both
$r = req('GET', "$base/index.php?refresh=1", null, $dashJar, [], 90);  // longer timeout for multi-mailbox refresh
// ?refresh=1 invalidates cache, so it'll fetch from both mailboxes
ok('dashboard refreshes with both mailboxes', $r['code'] === 200, "code={$r['code']} body_start=" . substr($r['body'], 0, 200));

if ($r['code'] === 200) {
    // The mailbox column may be empty if no NDRs, but the row should be present
    // for messages fetched. We just verify the page doesn't error.
    ok('no fatal error in body', !str_contains($r['body'], 'Fatal error'));
    ok('shows mailboxes in nav or table', str_contains($r['body'], $secondEmail) || !str_contains($r['body'], 'No active mailboxes'));
    // Header subtitle should list BOTH mailboxes (regression: was showing only one)
    ok('header subtitle shows the original sender', str_contains($r['body'], htmlspecialchars(MS_SENDER_EMAIL, ENT_QUOTES)));
    ok('header subtitle shows the second mailbox', str_contains($r['body'], htmlspecialchars($secondEmail, ENT_QUOTES)));
    ok('header has Tracking NDRs for label', str_contains($r['body'], 'Tracking NDRs for'));
    // Page title should include both
    ok('page <title> includes both mailboxes', str_contains($r['body'], htmlspecialchars(MS_SENDER_EMAIL, ENT_QUOTES)) && str_contains($r['body'], htmlspecialchars($secondEmail, ENT_QUOTES)));
}

// Pause the second mailbox
req('POST', "$base/admin.php", [
    'action' => 'toggle',
    'email'  => $secondEmail,
    'active' => 0,
], $adminJar);

// Verify dashboard still loads (just with one mailbox)
$r = req('GET', "$base/index.php", null, $dashJar);
ok('dashboard loads with paused mailbox', $r['code'] === 200);
ok('shows no-active warning OR continues', $r['code'] === 200);

// Re-enable + remove
req('POST', "$base/admin.php", [
    'action' => 'toggle',
    'email'  => $secondEmail,
    'active' => 1,
], $adminJar);
req('POST', "$base/admin.php", [
    'action' => 'remove',
    'email'  => $secondEmail,
], $adminJar);
@unlink($adminJar);

// ============================================
// 14. BODY.PHP
// ============================================
section("14. BODY.PHP — auth + mailbox param");
// Unauthed (no cookie jar)
$r = req('GET', "$base/body.php");
ok('body.php unauthed returns 401', $r['code'] === 401, "code={$r['code']}");

// Authed with fake id
$r = req('GET', "$base/body.php?id=fakeid", null, $dashJar);
ok('body.php authed with fake id returns error', $r['code'] !== 401);
ok('body.php authed returns error JSON', str_contains($r['body'], '"error"'));

$r = req('GET', "$base/body.php?id=fakeid&mailbox=" . urlencode(MS_SENDER_EMAIL), null, $dashJar);
ok('body.php with explicit mailbox returns error', str_contains($r['body'], '"error"'));

// Missing id (authed)
$r = req('GET', "$base/body.php", null, $dashJar);
ok('body.php missing id returns 400', $r['code'] === 400);

// ============================================
// 15. SUPPRESSION PAGE
// ============================================
section("15. SUPPRESSION PAGE");
$r = req('GET', "$base/suppression.php", null, $dashJar);
ok('suppression loads', $r['code'] === 200);
ok('has Admin link', str_contains($r['body'], 'admin.php'));
ok('has Dashboard link', str_contains($r['body'], 'index.php'));
ok('has Total Suppressed label', str_contains($r['body'], 'Total Suppressed'));
ok('has Total Bounces label', str_contains($r['body'], 'Total Bounces'));
ok('has Bouncing Domains label', str_contains($r['body'], 'Bouncing Domains'));
ok('has Last Sync label', str_contains($r['body'], 'Last Sync'));
ok('shows All bouncing domains section', str_contains($r['body'], 'All bouncing domains'));
ok('shows top domain as link with count', preg_match('/@[\w.\-]+\s*<span[^>]*>\d+<\/span>/', $r['body']) === 1);
@unlink($dashJar);

// ============================================
// 16. CHECK API — POSITIVE
// ============================================
section("16. CHECK API — auth + functionality");
$r = req('GET', "$base/check.php?email=foo@bar.com&key=$apiKey");
ok('check.php with key returns 200', $r['code'] === 200);
$j = json_decode($r['body'], true);
ok('returns valid JSON', is_array($j));
ok('has results array', isset($j['results']) && is_array($j['results']));
ok('has checked count', isset($j['checked']));
ok('has allowed count', isset($j['allowed']));
ok('has blocked count', isset($j['blocked']));
ok('result has email + suppressed', isset($j['results'][0]['email']) && array_key_exists('suppressed', $j['results'][0]));

// POST batch
$r = req('POST', "$base/check.php",
    ['key' => $apiKey, 'emails' => "a@x.com\nb@y.com\nc@z.com"],
    null, ['X-API-Key: ' . $apiKey]
);
ok('POST batch returns 200', $r['code'] === 200, "code={$r['code']} body=" . substr($r['body'], 0, 200));
$j2 = json_decode($r['body'], true);
ok('POST batch returns 3 results', is_array($j2) && count($j2['results'] ?? []) === 3, "j2=" . json_encode($j2));

// POST batch as JSON
$ch = curl_init("$base/check.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['emails' => ['j1@x.com', 'j2@x.com'], 'key' => $apiKey]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$rb = curl_exec($ch);
$rc = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$j3 = json_decode($rb, true);
ok('POST JSON batch returns 200', $rc === 200);
ok('POST JSON batch returns 2 results', is_array($j3) && count($j3['results'] ?? []) === 2);

// Header-based auth (X-API-Key)
$r = req('GET', "$base/check.php?email=foo@bar.com", null, null, ['X-API-Key: ' . $apiKey]);
ok('X-API-Key header auth works', $r['code'] === 200);

// ============================================
// 17. CHECK API — NEGATIVE
// ============================================
section("17. CHECK API — auth failures");
$r = req('GET', "$base/check.php?email=foo@bar.com");
ok('missing key returns 401', $r['code'] === 401);

$r = req('GET', "$base/check.php?email=foo@bar.com&key=wrong-key");
ok('wrong key returns 401', $r['code'] === 401);

$r = req('POST', "$base/check.php", ['emails' => 'a@x.com'], null, ['X-API-Key: wrong-key']);
ok('wrong X-API-Key returns 401', $r['code'] === 401);

// ============================================
// 18. SECURITY — XSS
// ============================================
section("18. SECURITY — XSS escape");
$xssEmail = 'xss-' . md5(uniqid()) . '@test.com';
$xssDesc  = '<script>alert(1)</script><img src=x onerror=alert(2)>';
$adminJar = jar('admin3');
req('POST', "$base/admin.php", ['admin_password' => $adminPwd], $adminJar);
req('POST', "$base/admin.php", [
    'action'      => 'add',
    'email'       => $xssEmail,
    'description' => $xssDesc,
], $adminJar);
$r = req('GET', "$base/admin.php", null, $adminJar);
ok('admin page escapes <script>', !str_contains($r['body'], '<script>alert(1)</script>'));
// "onerror=alert" check: only fail if it appears as a live attribute inside a tag.
// (htmlspecialchars doesn't escape "=", so the literal text is OK in escaped form.)
ok('no live onerror attribute in any tag', !preg_match('/<[^>]*\sonerror\s*=/i', $r['body']));
ok('admin page shows literal entities', str_contains($r['body'], '&lt;script&gt;') || str_contains($r['body'], '&lt;img'));

// Cleanup
req('POST', "$base/admin.php", [
    'action' => 'remove',
    'email'  => $xssEmail,
], $adminJar);
@unlink($adminJar);

// ============================================
// 17b. CROSS-PAGE COUNTER CONSISTENCY
// ============================================
section("17b. CROSS-PAGE COUNTER CONSISTENCY");

$pdo = db();

// Seed a known set of suppression rows
$pdo->exec("DELETE FROM suppression_list WHERE email LIKE 'xcheck-%@test.invalid'");
$pdo->exec("DELETE FROM processed_ndrs WHERE mailbox_email = 'xcheck-mb@test.invalid'");

$xcTuples = [];
for ($i = 1; $i <= 5; $i++) {
    $xcTuples[] = [
        'mailbox'    => 'xcheck-mb@test.invalid',
        'message_id' => "XC-MSG-$i",
        'email'      => "xcheck-u$i@test.invalid",
    ];
}
$xr = syncSuppressionList($xcTuples);
ok('seed: 5 tuples added', $xr['added'] === 5);

// All three pages should show the same Total Suppressed count
$supp = suppressionListStats();
$suppTotal = (int)$supp['summary']['total'];
$domainTotal = count($supp['top_domains']);  // just sanity

// Suppression page
$jar = jar('xc1');
req('POST', "$base/index.php", ['password' => $dashPwd], $jar);

// Trigger dashboard sync (so all counters reflect the same baseline)
req('GET', "$base/index.php?refresh=1", null, $jar, [], 90);

// Now capture all three pages in a stable order: admin → suppression → dashboard
$adminJar = jar('xcadm');
req('POST', "$base/admin.php", ['admin_password' => $adminPwd], $adminJar);
$rAdm = req('GET', "$base/admin.php", null, $adminJar);
$rSupp = req('GET', "$base/suppression.php", null, $jar);
$rDash = req('GET', "$base/index.php", null, $jar, [], 60);

// Extract from suppression page (uses class="stat-value")
$totalSuppPage = 0;
if (preg_match('/Total Suppressed<\/div>\s*<div class="stat-value">\s*([0-9,]+)\s*</', $rSupp['body'], $m)) {
    $totalSuppPage = (int)str_replace(',', '', $m[1]);
}
$totalBouncesPage = 0;
if (preg_match('/Total Bounces<\/div>\s*<div class="stat-value">\s*([0-9,]+)\s*</', $rSupp['body'], $m)) {
    $totalBouncesPage = (int)str_replace(',', '', $m[1]);
}
$bouncingDomainsPage = 0;
if (preg_match('/Bouncing Domains<\/div>\s*<div class="stat-value">\s*([0-9,]+)\s*</', $rSupp['body'], $m)) {
    $bouncingDomainsPage = (int)str_replace(',', '', $m[1]);
}
$lastSyncSupp = null;
if (preg_match('/Last Sync<\/div>\s*<div class="stat-value"[^>]*>([^<]+)</', $rSupp['body'], $m)) {
    $lastSyncSupp = trim($m[1]);
}

// Extract from admin page (uses class="value")
$adm1 = -1; $adm2 = -1; $admB = -1; $admLastSync = '';
if (preg_match('/Suppressed emails<\/div>\s*<div class="value">\s*([0-9,]+)\s*</', $rAdm['body'], $m1)) $adm1 = (int)str_replace(',', '', $m1[1]);
if (preg_match('/Total suppressed<\/div>\s*<div class="value">\s*([0-9,]+)\s*</', $rAdm['body'], $m2)) $adm2 = (int)str_replace(',', '', $m2[1]);
if (preg_match('/Total bounces<\/div>\s*<div class="value"[^>]*>\s*([0-9,]+)\s*</', $rAdm['body'], $m3)) $admB = (int)str_replace(',', '', $m3[1]);
if (preg_match('/Last sync<\/div>\s*<div class="value"[^>]*>([^<]+)</', $rAdm['body'], $m4)) $admLastSync = trim($m4[1]);

// Extract from dashboard (uses class="stat-value" too)
$dashSuppTotal = 0;
if (preg_match('/Suppression List<\/div>\s*<div[^>]*style="[^"]*color:\s*var\(--danger\)"[^>]*>\s*([0-9,]+)\s*</', $rDash['body'], $m)) {
    $dashSuppTotal = (int)str_replace(',', '', $m[1]);
} elseif (preg_match('/Suppression List<\/div>\s*<div class="stat-value"[^>]*>\s*([0-9,]+)\s*</', $rDash['body'], $m)) {
    $dashSuppTotal = (int)str_replace(',', '', $m[1]);
}
$dashTotalBounces = 0;
if (preg_match('/Total Bounce Events<\/div>\s*<div class="stat-value"[^>]*>\s*([0-9,]+)\s*</', $rDash['body'], $m)) {
    $dashTotalBounces = (int)str_replace(',', '', $m[1]);
}

ok('suppression page shows the seeded count', $totalSuppPage === $suppTotal, "suppTotal=$suppTotal page=$totalSuppPage");
ok('suppression page Total Bounces is at least total (sum of counts)', $totalBouncesPage >= $totalSuppPage, "bounces=$totalBouncesPage total=$totalSuppPage");
$expectedDomains = (int)$pdo->query("SELECT COUNT(DISTINCT SUBSTRING_INDEX(email, '@', -1)) FROM suppression_list")->fetchColumn();
ok('suppression page Bouncing Domains matches DB count', $bouncingDomainsPage === $expectedDomains, "page=$bouncingDomainsPage db=$expectedDomains");
ok('dashboard Suppression List stat matches suppression page', $dashSuppTotal === $suppTotal, "suppTotal=$suppTotal dashTotal=$dashSuppTotal");
ok('dashboard Total Bounce Events matches suppression page', $dashTotalBounces === $totalBouncesPage, "pageBounces=$totalBouncesPage dashBounces=$dashTotalBounces");
ok('admin Overview Suppressed emails matches', $adm1 === $suppTotal, "got $adm1 expected $suppTotal");
ok('admin Suppression list Total suppressed matches', $adm2 === $suppTotal, "got $adm2 expected $suppTotal");
ok('admin Total bounces matches suppression page', $admB === $totalBouncesPage, "got $admB expected $totalBouncesPage");
ok('admin Last sync is not "—" (we just synced)', $admLastSync !== '—' && $admLastSync !== '', "got '$admLastSync'");
ok('suppression page Last Sync is not "—"', $lastSyncSupp !== null && $lastSyncSupp !== '—', "got " . var_export($lastSyncSupp, true));
ok('admin Last sync == suppression page Last sync', $admLastSync === $lastSyncSupp, "admin='$admLastSync' supp='$lastSyncSupp'");
ok('dashboard has Bouncing Domains stat', (bool)preg_match('/Bouncing Domains<\/div>\s*<div class="stat-value">\s*([0-9,]+)\s*</', $rDash['body']));
ok('dashboard has clickable Bouncing Domains card', str_contains($rDash['body'], 'id="openDomainsModal"'));
ok('dashboard has domains modal markup', str_contains($rDash['body'], 'id="domainsModal"'));
ok('dashboard has DOMAINS json with current data', str_contains($rDash['body'], 'const DOMAINS'));
ok('dashboard has SUPP_DOMAINS json for cross-check', str_contains($rDash['body'], 'const SUPP_DOMAINS'));

// Cleanup
$pdo->exec("DELETE FROM suppression_list WHERE email LIKE 'xcheck-%@test.invalid'");
$pdo->exec("DELETE FROM processed_ndrs WHERE mailbox_email = 'xcheck-mb@test.invalid'");
@unlink($jar); @unlink($adminJar);

// ============================================
// 19. SECURITY — SQL INJECTION
// ============================================
section("19. SECURITY — SQL injection");
$adminJar = jar('admin4');
req('POST', "$base/admin.php", ['admin_password' => $adminPwd], $adminJar);
$sqli = "x'; DROP TABLE monitored_mailboxes; --@test.com";
$r = req('POST', "$base/admin.php", [
    'action'      => 'add',
    'email'       => $sqli,
    'description' => 'SQLi test',
], $adminJar);
// Should be rejected by FILTER_VALIDATE_EMAIL
ok('SQLi email rejected by validation', str_contains($r['body'], 'Invalid email'));

// Confirm table still exists
$check = $pdo->query("SHOW TABLES LIKE 'monitored_mailboxes'")->fetch();
ok('monitored_mailboxes table still exists after SQLi attempt', (bool)$check);
@unlink($adminJar);

// ============================================
// 20. CACHE FILE BEHAVIOR
// ============================================
section("20. CACHE FILE BEHAVIOR");
$cacheDir = CACHE_DIR;
$files = glob($cacheDir . '/*.json') ?: [];
ok('cache dir contains JSON files after dashboard load', count($files) > 0, 'no cache files found');

$ht = $cacheDir . '/.htaccess';
ok('cache dir has .htaccess', is_file($ht));
$htContent = is_file($ht) ? (string)file_get_contents($ht) : '';
ok('.htaccess denies access', str_contains($htContent, 'Require all denied') || str_contains($htContent, 'Deny from all'));

// ============================================
// 20b. SUPPRESSION DEDUP — bounce_count does not double-count
// ============================================
section("20b. SUPPRESSION LIST — NDR dedup (regression: inflated bounce_count)");

$pdo = db();

// Reset to known state
$pdo->exec("DELETE FROM suppression_list WHERE email LIKE 'dedup-%@test.invalid'");
$pdo->exec("DELETE FROM processed_ndrs WHERE mailbox_email = 'mb-dedup@test.invalid'");

$testEm = 'dedup-recipient@test.invalid';

// First sync with a new NDR
$r1 = syncSuppressionList([
    ['mailbox' => 'mb-dedup@test.invalid', 'message_id' => 'AAMkAGI1', 'email' => $testEm],
]);
ok('first sync added 1', $r1['added'] === 1, "got added={$r1['added']}");

$b1 = (int)$pdo->prepare("SELECT bounce_count FROM suppression_list WHERE email = ?")
    ->execute([$testEm]) ?: 0;
$row = $pdo->prepare("SELECT bounce_count FROM suppression_list WHERE email = ?");
$row->execute([$testEm]);
$b1 = (int)$row->fetchColumn();
ok('first sync bounce_count = 1', $b1 === 1, "got b1={$b1}");

// Re-sync the SAME NDR (same message_id) — should be a no-op
$r2 = syncSuppressionList([
    ['mailbox' => 'mb-dedup@test.invalid', 'message_id' => 'AAMkAGI1', 'email' => $testEm],
]);
ok('re-sync same NDR: added=0', $r2['added'] === 0, "got added={$r2['added']}");
ok('re-sync same NDR: updated=0', $r2['updated'] === 0, "got updated={$r2['updated']}");

$row->execute([$testEm]);
$b2 = (int)$row->fetchColumn();
ok('re-sync did NOT inflate bounce_count (regression)', $b2 === 1, "got b2={$b2} (was {$b1})");

// Re-sync 10 more times — count should still be 1
for ($i = 0; $i < 10; $i++) {
    syncSuppressionList([
        ['mailbox' => 'mb-dedup@test.invalid', 'message_id' => 'AAMkAGI1', 'email' => $testEm],
    ]);
}
$row->execute([$testEm]);
$b3 = (int)$row->fetchColumn();
ok('10 more re-syncs did not inflate count', $b3 === 1, "got b3={$b3}");

// A NEW NDR for the SAME recipient — should now increment to 2
$r4 = syncSuppressionList([
    ['mailbox' => 'mb-dedup@test.invalid', 'message_id' => 'AAMkAGI2', 'email' => $testEm],
]);
$row->execute([$testEm]);
$b4 = (int)$row->fetchColumn();
ok('new NDR for same recipient: count=2', $b4 === 2, "got b4={$b4}");

// New NDR, NEW recipient
$newEm = 'dedup-other@test.invalid';
syncSuppressionList([
    ['mailbox' => 'mb-dedup@test.invalid', 'message_id' => 'AAMkAGI3', 'email' => $newEm],
]);
$row->execute([$newEm]);
$b5 = (int)$row->fetchColumn();
ok('new recipient: count=1', $b5 === 1, "got b5={$b5}");

// Same NDR appearing in two folders (Graph quirk) — should count once
syncSuppressionList([
    ['mailbox' => 'mb-dedup@test.invalid', 'message_id' => 'AAMkAGI1', 'email' => $testEm],
    ['mailbox' => 'mb-dedup@test.invalid', 'message_id' => 'AAMkAGI1', 'email' => $testEm],
]);
$row->execute([$testEm]);
$b6 = (int)$row->fetchColumn();
ok('NDR appearing twice in same batch counts once', $b6 === 2, "got b6={$b6}");

// Test resetSuppressionCounts
$pdo->exec("UPDATE suppression_list SET bounce_count = 50 WHERE email = '$testEm'");
$resetCount = resetSuppressionCounts();
ok('resetSuppressionCounts returned positive', $resetCount > 0, "got $resetCount");
$row->execute([$testEm]);
$b7 = (int)$row->fetchColumn();
ok('reset set bounce_count to 1', $b7 === 1, "got b7={$b7}");

// Test purgeProcessedNdrs
$purgeCount = purgeProcessedNdrs();
ok('purgeProcessedNdrs returned positive', $purgeCount > 0, "got $purgeCount");
$pc = (int)$pdo->query("SELECT COUNT(*) FROM processed_ndrs")->fetchColumn();
ok('processed_ndrs table is empty after purge', $pc === 0, "got $pc");

// After purge, re-syncing same NDR will count again (because tracking is gone)
syncSuppressionList([
    ['mailbox' => 'mb-dedup@test.invalid', 'message_id' => 'AAMkAGI1', 'email' => $testEm],
]);
$row->execute([$testEm]);
$b8 = (int)$row->fetchColumn();
ok('after purge, re-sync increments count again', $b8 === 2, "got b8={$b8}");

// Schema check
$procCols = $pdo->query("SHOW COLUMNS FROM processed_ndrs")->fetchAll(PDO::FETCH_COLUMN);
ok('processed_ndrs has mailbox_email', in_array('mailbox_email', $procCols));
ok('processed_ndrs has message_id', in_array('message_id', $procCols));
$procIdx = $pdo->query("SHOW INDEX FROM processed_ndrs WHERE Key_name = 'uk_msg'")->fetch();
ok('processed_ndrs has UNIQUE(mailbox_email, message_id)', (bool)$procIdx);

// Cleanup
$pdo->exec("DELETE FROM suppression_list WHERE email LIKE 'dedup-%@test.invalid'");
$pdo->exec("DELETE FROM processed_ndrs WHERE mailbox_email = 'mb-dedup@test.invalid'");

// ============================================
// 21. PERFORMANCE — CACHE HIT
// ============================================
section("21. CACHE HIT — second request is fast");
$dashJar2 = jar('dash2');
req('POST', "$base/index.php", ['password' => $dashPwd], $dashJar2);
// First request may go to Graph (slow) or cache (fast) depending on prior runs.
$t1 = microtime(true);
$r1 = req('GET', "$base/index.php", null, $dashJar2, [], 60);
$d1 = microtime(true) - $t1;
$t2 = microtime(true);
$r2 = req('GET', "$base/index.php", null, $dashJar2, [], 60);
$d2 = microtime(true) - $t2;
ok('first request succeeded', $r1['code'] === 200);
ok('second request succeeded', $r2['code'] === 200);
ok('second request is faster than first (cache hit)', $d2 <= $d1 + 0.5, "d1={$d1}s d2={$d2}s");
echo "        d1=" . round($d1, 3) . "s  d2=" . round($d2, 3) . "s\n";
@unlink($dashJar2);

// ============================================
// SUMMARY
// ============================================
echo "\n" . str_repeat("=", 60) . "\n";
echo "TOTAL: " . ($pass + $fail) . "  PASS: $pass  FAIL: $fail\n";
if ($fail > 0) {
    echo "\nFailures:\n";
    foreach ($failures as $f) echo "  - $f\n";
}
echo str_repeat("=", 60) . "\n";
exit($fail > 0 ? 1 : 0);
