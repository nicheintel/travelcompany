<?php
declare(strict_types=1);

// Admin live updates: open admin pages ask every 15 seconds what's new (see [data-live] in assets/app.js).
// A background check: it doesn't count as activity, so staff are still signed out after 12 idle hours.
const LL_PASSIVE = true;
require dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$u = current_user();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close(); // read only: don't hold up the admin's other requests
}
if (!$u || !$u['is_admin'] || !empty($u['must_change_password'])) {
    http_response_code(403);
    echo '{"signed_out":true}';
    exit;
}

$tops = admin_live_tops();
// What arrived since the page's last check ("since" = the newest item it knew of, per kind)
$since = [];
foreach (explode(',', as_str($_GET['since'] ?? '')) as $pair) {
    [$k, $v] = array_pad(explode(':', $pair, 2), 2, '');
    if (isset($tops[$k]) && ctype_digit($v)) $since[$k] = (int) $v;
}
$plural = fn (int $n, string $one, string $many) => $n === 1 ? $one : $n . ' ' . $many;
$new = [];
foreach ($since as $k => $from) {
    if ($tops[$k] <= $from) continue;
    switch ($k) {
        case 'applications':
            $n = (int) db_val('SELECT COUNT(*) FROM applications WHERE id > ?', [$from]);
            $a = db_one('SELECT a.first_name, a.last_name, a.job_id, u.name, j.title FROM applications a JOIN users u ON u.id = a.user_id
                LEFT JOIN jobs j ON j.id = a.job_id ORDER BY a.id DESC LIMIT 1');
            $who = $a ? (trim($a['first_name'] . ' ' . $a['last_name']) ?: (string) $a['name']) : '';
            $new[$k] = ['n' => $n, 'title' => $plural($n, 'New application', 'new applications'),
                'text' => $who . ($a ? ' · ' . applicant_label($a) : ''), 'url' => url('admin/applications.php?status=new')];
            break;
        case 'onboarding':
            $n = (int) db_val("SELECT COUNT(*) FROM onboarding WHERE stage = 'review' AND UNIX_TIMESTAMP(submitted_at) > ?", [$from]);
            $who = (string) db_val("SELECT u.name FROM onboarding o JOIN users u ON u.id = o.user_id WHERE o.stage = 'review' ORDER BY o.submitted_at DESC LIMIT 1");
            $new[$k] = ['n' => $n, 'title' => $n === 1 ? 'Documents ready for review' : $n . ' drivers ready for review',
                'text' => $who !== '' ? $who . ' sent their onboarding documents' : '', 'url' => url('admin/onboarding.php')];
            break;
        case 'chats':
            $n = (int) db_val("SELECT COUNT(DISTINCT thread_id) FROM support_messages WHERE sender = 'user' AND id > ?", [$from]);
            $m = db_one("SELECT m.thread_id, m.body, t.name FROM support_messages m JOIN support_threads t ON t.id = m.thread_id
                WHERE m.sender = 'user' ORDER BY m.id DESC LIMIT 1");
            $new[$k] = ['n' => $n, 'title' => $n === 1 ? 'New chat message' : 'New messages in ' . $n . ' chats',
                'text' => $m ? $m['name'] . ': ' . mb_strimwidth(preg_replace('/\s+/', ' ', (string) $m['body']), 0, 80, '…') : '',
                'url' => url('admin/chats.php' . ($m ? '?t=' . (int) $m['thread_id'] : ''))];
            break;
        case 'messages':
            $n = (int) db_val('SELECT COUNT(*) FROM messages WHERE id > ?', [$from]);
            $m = db_one('SELECT name, topic FROM messages ORDER BY id DESC LIMIT 1');
            $new[$k] = ['n' => $n, 'title' => $plural($n, 'New contact message', 'new contact messages'),
                'text' => $m ? $m['name'] . ($m['topic'] !== '' ? ' · ' . ucfirst((string) $m['topic']) : '') : '', 'url' => url('admin/messages.php')];
            break;
        case 'partners':
            $n = (int) db_val('SELECT COUNT(*) FROM partner_requests WHERE id > ?', [$from]);
            $p = db_one('SELECT first_name, last_name, company FROM partner_requests ORDER BY id DESC LIMIT 1');
            $new[$k] = ['n' => $n, 'title' => $plural($n, 'New partner request', 'new partner requests'),
                'text' => $p ? $p['company'] . ' · ' . trim($p['first_name'] . ' ' . $p['last_name']) : '', 'url' => url('admin/partners.php')];
            break;
        case 'members':
            $n = (int) db_val('SELECT COUNT(*) FROM users WHERE is_admin = 0 AND id > ?', [$from]);
            $m = db_one('SELECT name, account_type, city FROM users WHERE is_admin = 0 ORDER BY id DESC LIMIT 1');
            $new[$k] = ['n' => $n, 'title' => $plural($n, 'New sign-up', 'new sign-ups'),
                'text' => $m ? $m['name'] . ' · ' . explode(' (', ACCOUNT_TYPES[$m['account_type']] ?? 'Member')[0] . ($m['city'] !== '' ? ', ' . $m['city'] : '') : '',
                'url' => url('admin/drivers.php')];
            break;
    }
}

echo json_encode(['counts' => admin_counts(), 'tops' => $tops, 'new' => $new], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
