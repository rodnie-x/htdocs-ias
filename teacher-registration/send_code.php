<?php
// >>> PLACE AT: teacher-registration/send_code.php (replace the old file)  [batch 6: per-IP rate limit]
/**
 * "Send code" button on the login form.
 * Always answers with the same message whether or not the Gmail is registered,
 * so it can't be used to find out who has an account.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/rate_limit.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function jsonOut(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(405, ['ok' => false, 'message' => 'Use the Send code button on the login page.']);
}
if (!csrfValid()) {
    jsonOut(419, ['ok' => false, 'message' => 'Your session expired. Refresh the page and try again.']);
}

// Per-IP cap (database): cannot be beaten by deleting cookies. Protects the Gmail daily sending limit.
if (!rateLimitAllow('treg_code', 10, 3600)) {
    jsonOut(429, ['ok' => false, 'message' => 'Too many code requests from this network. Try again later.']);
}

// Per-browser-session cap: at most 8 requests in 10 minutes.
$now = time();
$recent = array_filter($_SESSION['code_requests'] ?? [], fn($t) => $now - $t < 600);
if (count($recent) >= 8) {
    $_SESSION['code_requests'] = $recent;
    jsonOut(429, ['ok' => false, 'message' => 'Too many code requests. Wait a few minutes and try again.']);
}
$recent[] = $now;
$_SESSION['code_requests'] = array_values($recent);

$email = strtolower(trim(strIn($_POST, 'email')));
if (!isGmail($email)) {
    jsonOut(422, ['ok' => false, 'message' => 'Enter your Gmail address (ending in @gmail.com) first.']);
}

$response = [
    'ok'       => true,
    'message'  => 'If that Gmail is registered, a login code is on its way. It expires in ' . CODE_TTL_MINUTES . ' minutes.',
    'cooldown' => CODE_RESEND_SECONDS,
];

$account = findAccountByEmail($email);
if ($account && !accountIsLocked($account)) {
    $result = issueLoginCode($account);
    if ($result['debug_code'] !== null) {
        $response['debug_code'] = $result['debug_code'];   // dev mode only (APP_DEBUG + MAIL_MODE 'log')
    }
}

jsonOut(200, $response);