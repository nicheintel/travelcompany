<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$me = require_admin();
$counts = admin_counts();
$members = (int) db_val('SELECT COUNT(*) FROM users WHERE is_admin = 0');
$membersWeek = (int) db_val('SELECT COUNT(*) FROM users WHERE is_admin = 0 AND created_at > NOW() - INTERVAL 7 DAY');
$appsTotal = (int) db_val('SELECT COUNT(*) FROM applications');
$appsWeek = (int) db_val('SELECT COUNT(*) FROM applications WHERE created_at > NOW() - INTERVAL 7 DAY');
$openJobs = (int) db_val("SELECT COUNT(*) FROM jobs WHERE status = 'open'");
$inbox = $counts['chats'] + $counts['messages'] + $counts['partners'];
$unconfirmed = (int) db_val('SELECT COUNT(*) FROM users WHERE is_admin = 0 AND email_verified_at IS NULL');
$insurance = (int) db_val('SELECT COUNT(*) FROM driver_profiles WHERE insurance_expires IS NOT NULL AND insurance_expires < CURDATE() + INTERVAL 30 DAY');

$latest = db_all('SELECT a.*, u.name, u.email, j.title FROM applications a JOIN users u ON u.id = a.user_id LEFT JOIN jobs j ON j.id = a.job_id ORDER BY a.created_at DESC LIMIT 6');
$byEquip = db_all("SELECT equipment, COUNT(*) n FROM driver_profiles WHERE equipment <> '' GROUP BY equipment ORDER BY n DESC");
$equipMax = max(1, ...array_map(fn ($r) => (int) $r['n'], $byEquip ?: [['n' => 1]]));

$hour = (int) date('G');
$hello = ($hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening')) . ', ' . (trim((string) strtok((string) $me['name'], ' ')) ?: 'there');

$kpis = [
    ['clipboard', 'New applications', $counts['applications'], $appsWeek . ' this week · ' . $appsTotal . ' total', 'admin/applications.php?status=new'],
    ['truck', 'Drivers & members', $members, $membersWeek ? '+' . $membersWeek . ' joined this week' : 'No new sign-ups this week', 'admin/drivers.php'],
    ['briefcase', 'Open job posts', $openJobs, 'Shown on the Careers page', 'admin/jobs.php'],
    ['mail', 'Waiting in your inbox', $inbox, $inbox ? 'Chats, messages and partner requests' : 'You’re all caught up', $counts['chats'] ? 'admin/chats.php' : ($counts['messages'] ? 'admin/messages.php' : 'admin/partners.php')],
];
$todo = array_filter([
    ['check', 'Onboarding documents to review', $counts['onboarding'], 'admin/onboarding.php?stage=review'],
    ['clipboard', 'Applications to review', $counts['applications'], 'admin/applications.php?status=new'],
    ['chat', 'Support chats waiting for a reply', $counts['chats'], 'admin/chats.php'],
    ['mail', 'Unread contact messages', $counts['messages'], 'admin/messages.php'],
    ['handshake', 'New partner requests', $counts['partners'], 'admin/partners.php?status=new'],
    ['shield', 'Insurance expired or expiring within 30 days', $insurance, 'admin/drivers.php'],
    ['user', 'Members who haven’t confirmed their email', $unconfirmed, 'admin/drivers.php'],
], fn ($t) => $t[2] > 0);

page_header('Admin overview');
admin_open('overview');
echo admin_head($hello, 'Here’s what’s happening at LamazonLoads and what needs your attention today.',
    '<a class="btn btn-ghost" href="' . e(url('admin/jobs.php?new=1')) . '">' . icon('plus') . ' New job post</a>'
    . '<a class="btn btn-accent" href="' . e(url('admin/drivers.php#add-member')) . '">' . icon('plus') . ' Add member</a>');
?>
<div class="kpis">
  <?php foreach ($kpis as [$ic, $label, $n, $sub, $href]): ?>
    <a class="card kpi" href="<?= e(url($href)) ?>">
      <span class="kpi-ico"><?= icon($ic) ?></span>
      <span class="kpi-label"><?= e($label) ?></span>
      <b class="kpi-num"><?= $n ?></b>
      <small class="kpi-sub"><?= e($sub) ?></small>
    </a>
  <?php endforeach; ?>
</div>

<div class="ov-grid">
  <section class="card panel">
    <header class="panel-head"><h2><?= icon('clipboard') ?>Latest applications</h2><a href="<?= e(url('admin/applications.php')) ?>">See all <?= icon('arrow') ?></a></header>
    <?php if (!$latest): ?>
      <div class="panel-body"><div class="empty">No applications yet. Share your Careers page to start recruiting.</div></div>
    <?php else: ?>
      <div class="panel-body flush ov-list">
        <?php foreach ($latest as $a):
            $name = trim($a['first_name'] . ' ' . $a['last_name']) ?: $a['name']; ?>
          <a class="ov-row" href="<?= e(url('admin/applications.php?id=' . (int) $a['id'])) ?>">
            <span class="ov-av" aria-hidden="true"><?= e(strtoupper(mb_substr($name, 0, 1))) ?></span>
            <span class="ov-who"><b><?= e($name) ?></b><small title="<?= e(applicant_label($a)) ?>"><?= e(applicant_label($a)) ?></small></span>
            <span class="ov-date"><?= e(fmt_date($a['created_at'], 'M j')) ?></span>
            <span class="ov-status"><?= status_badge($a['status']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <div class="ov-side">
    <section class="card panel">
      <header class="panel-head"><h2><?= icon('star') ?>Needs your attention</h2></header>
      <?php if (!$todo): ?>
        <div class="panel-body"><p class="ov-clear"><?= icon('check') ?>All caught up. Nothing is waiting for you.</p></div>
      <?php else: ?>
        <div class="panel-body flush">
          <?php foreach ($todo as [$ic, $label, $n, $href]): ?>
            <a class="todo" href="<?= e(url($href)) ?>"><span class="todo-ico"><?= icon($ic) ?></span><span class="todo-label"><?= e($label) ?></span><b class="todo-n"><?= $n ?></b></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="card panel">
      <header class="panel-head"><h2><?= icon('truck') ?>Drivers by equipment</h2><small><?= array_sum(array_column($byEquip, 'n')) ?> with a profile</small></header>
      <div class="panel-body">
        <?php if (!$byEquip): ?><p class="muted mb-0">No driver profiles yet.</p><?php else: ?>
          <ul class="bars">
            <?php foreach ($byEquip as $r): ?>
              <li><span class="bar-label"><?= e(EQUIPMENT[$r['equipment']] ?? $r['equipment']) ?></span><span class="bar"><i style="width:<?= round((int) $r['n'] / $equipMax * 100) ?>%"></i></span><b><?= (int) $r['n'] ?></b></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>
<?php dash_close(); page_footer();
