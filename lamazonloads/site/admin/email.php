<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

// Staff: is email working? Shows the settings, the domain's email records, and sends a test email.
$me = require_admin();
$result = null;
$to = (string) $me['email'];
if (is_post()) {
    csrf_check();
    $to = strtolower(post('to', 190));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $result = [false, 'Please enter a valid email address.'];
    } else {
        [$text, $html] = email_body('Test email', ['This is a test from your LamazonLoads website (Admin → Email check).', 'If you can read this, sign-up confirmations, chat and application emails can reach this address.']);
        $ok = send_mail($to, 'LamazonLoads test email', $text, $html, support_email());
        $result = [$ok, $ok ? '' : (mail_last_error() ?: 'Unknown error.')];
    }
}

$transport = mail_transport();
$from = mail_from();
$domain = email_domain($from);
$dns = function (string $name, int $type): ?array {
    $r = @dns_get_record($name, $type);
    return $r === false ? null : $r;
};
$mx = $dns($domain, DNS_MX);
$txt = $dns($domain, DNS_TXT);
$spf = $txt === null ? null : array_values(array_filter(array_map(fn ($r) => (string) ($r['txt'] ?? ''), $txt), fn ($t) => str_starts_with(strtolower($t), 'v=spf1')));
$dmarc = $dns('_dmarc.' . $domain, DNS_TXT);
$dkim = [];
foreach (['hostingermail1', 'hostingermail2', 'hostingermail3'] as $sel) {
    $r = $dns($sel . '._domainkey.' . $domain, DNS_CNAME | DNS_TXT);
    if ($r) {
        $dkim[] = $sel;
    }
}
$unconfirmed = (int) db_val('SELECT COUNT(*) FROM users WHERE is_admin = 0 AND email_verified_at IS NULL');

// Plain-words help for the usual errors
$hint = function (string $err): string {
    $e = strtolower($err);
    if (str_contains($e, '535') || str_contains($e, 'authentication')) return 'The mailbox password is wrong. Copy it again from hPanel → Emails → info@lamazonloads.com (or reset it there), and paste it into config.local.php as smtp_pass.';
    if (str_contains($e, "can't connect") || str_contains($e, 'connection')) return "The website couldn't reach the mail server. Check smtp_host (smtp.hostinger.com) and smtp_port (465) in config.local.php.";
    if (str_contains($e, 'mail() returned false')) return "Hostinger's basic mail refused the message. Set up the mailbox password (smtp_pass) in config.local.php; that's the reliable way.";
    if (str_contains($e, '550') || str_contains($e, '553') || str_contains($e, 'sender')) return 'The mail server refused the sender address. smtp_user and the sending address must be the same mailbox, e.g. info@lamazonloads.com.';
    return 'Check the email settings in config.local.php (smtp_user, smtp_pass), and that the mailbox exists in hPanel → Emails.';
};
$row = fn (string $label, bool|null $ok, string $value, string $note = '') =>
    '<tr><th>' . e($label) . '</th><td>' . ($ok === null ? '<span class="badge badge-closed">Unknown</span>' : ($ok ? '<span class="badge badge-open">OK</span>' : '<span class="badge badge-reviewing">Check</span>'))
    . ' ' . $value . ($note !== '' ? '<br><span class="hint">' . e($note) . '</span>' : '') . '</td></tr>';

page_header('Email check');
admin_open('email');
?>
<h1>Email check</h1>
<p class="muted">Sign-up confirmations, chat replies and application alerts all use these settings.</p>

<?php if ($result): ?>
  <?php if ($result[0]): ?>
    <div class="alert alert-success">Test email sent to <?= e($to) ?>. It should arrive within a minute.<?= $transport === 'mail' ? " (Sent with Hostinger's basic mail: if it doesn't arrive, set up the mailbox password below.)" : '' ?></div>
  <?php else: ?>
    <div class="alert alert-error"><b>The test email could not be sent.</b><br>What to do: <?= e($hint($result[1])) ?><br><span class="hint">Mail server said: <?= e($result[1]) ?></span></div>
  <?php endif; ?>
<?php endif; ?>

<div class="card pad">
  <h3 class="mt-0">Send a test email</h3>
  <form method="post" action="<?= e(url('admin/email.php')) ?>" class="row-actions">
    <?= csrf_field() ?>
    <input type="email" name="to" value="<?= e($to) ?>" maxlength="190" aria-label="Send the test to" style="max-width:340px">
    <button class="btn btn-primary" type="submit">Send test</button>
  </form>
</div>

<div class="card pad">
  <h3 class="mt-0">Settings</h3>
  <div class="table-wrap"><table class="kv"><tbody>
    <?= $row('How emails are sent', $transport === 'smtp',
        $transport === 'smtp' ? 'Through your mailbox <b>' . e((string) config('smtp_user')) . '</b> (' . e((string) config('smtp_host')) . ':' . (int) config('smtp_port') . ')'
            : ($transport === 'file' ? 'Saved to storage/mail (testing only, nothing is sent)' : "Hostinger's basic PHP mail (no mailbox password set)"),
        $transport === 'smtp' ? '' : "Recommended: create info@lamazonloads.com in hPanel → Emails and put its password in config.local.php as smtp_pass.") ?>
    <?= $row('Sent from', $from !== '', e($from)) ?>
    <?= $row('Alerts go to', true, e(support_email()), 'Chat and application alerts. Change with support_email or contact_email in config.local.php.') ?>
    <?= $row('Members waiting to confirm', $unconfirmed === 0, $unconfirmed . ' <a href="' . e(url('admin/drivers.php')) . '">See members</a>', $unconfirmed ? 'You can confirm someone by hand on their member page (Mark email as confirmed).' : '') ?>
  </tbody></table></div>
</div>

<div class="card pad">
  <h3 class="mt-0">Your domain's email records (<?= e($domain) ?>)</h3>
  <div class="table-wrap"><table class="kv"><tbody>
    <?= $row('Receives email (MX)', $mx === null ? null : (bool) $mx, $mx ? e(implode(', ', array_map(fn ($r) => (string) $r['target'], $mx))) : 'none found') ?>
    <?= $row('Allowed senders (SPF)', $spf === null ? null : (bool) $spf, $spf ? '<code>' . e($spf[0]) . '</code>' : 'none found', $spf ? '' : 'Hostinger adds this when the mailbox is created. Without it, emails may be refused.') ?>
    <?= $row('Signature (DKIM)', $dkim !== [], $dkim ? e(implode(', ', $dkim)) : 'not found', $dkim ? '' : 'Usually added automatically by Hostinger for its email. Check hPanel → Emails → DNS settings.') ?>
    <?= $row('Policy (DMARC)', $dmarc === null ? null : (bool) $dmarc, $dmarc ? '<code>' . e((string) ($dmarc[0]['txt'] ?? '')) . '</code>' : 'none found', $dmarc ? '' : 'Optional, but helps delivery to Gmail and Yahoo.') ?>
  </tbody></table></div>
</div>
<?php dash_close(); page_footer();
