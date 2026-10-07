<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$admin = require_admin();
$tz = site_timezone();
$now = new DateTimeImmutable('now', $tz);

// ---- Numbers ----
$byStatus = array_fill_keys(array_keys(STATUSES), 0);
foreach (db_all('SELECT status, COUNT(*) AS n FROM requests GROUP BY status') as $r) {
    $byStatus[$r['status']] = (int) $r['n'];
}
$total = array_sum($byStatus);
$waiting = (int) db_value("SELECT COUNT(*) FROM requests WHERE status = 'new' AND created_at < ?", [gmdate('Y-m-d H:i:s', time() - 86400)]);
$quotedValue = (float) db_value("SELECT COALESCE(SUM(quoted_amount), 0) FROM requests WHERE status IN ('quoted', 'in_progress')");
$monthStartUtc = $now->modify('first day of this month')->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
$launchedThisMonth = (int) db_value("SELECT COUNT(*) FROM requests WHERE status = 'launched' AND launched_at >= ?", [$monthStartUtc]);
$wonValue = (float) db_value("SELECT COALESCE(SUM(quoted_amount), 0) FROM requests WHERE status = 'launched'");

// ---- Requests per week, last 12 weeks (weeks start on Monday, in your time zone) ----
$weekStart = $now->modify('monday this week')->setTime(0, 0);
$weeks = [];
for ($i = 11; $i >= 0; $i--) {
    $start = $weekStart->modify("-$i weeks");
    $weeks[$start->format('Y-m-d')] = ['start' => $start, 'count' => 0];
}
$firstUtc = $weekStart->modify('-11 weeks')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
foreach (db_all('SELECT created_at FROM requests WHERE created_at >= ?', [$firstUtc]) as $r) {
    $local = (new DateTimeImmutable($r['created_at'], new DateTimeZone('UTC')))->setTimezone($tz);
    $key = $local->modify('monday this week')->format('Y-m-d');
    if (isset($weeks[$key])) $weeks[$key]['count']++;
}
$last12 = array_sum(array_column($weeks, 'count'));

$needsReply = db_all("SELECT id, ref, name, business_name, package_name, created_at FROM requests WHERE status = 'new' ORDER BY created_at ASC LIMIT 6");
$activity = db_all(
    'SELECT e.kind, e.body, e.created_at, r.id AS request_id, r.ref, r.name, a.name AS admin_name
     FROM request_events e JOIN requests r ON r.id = e.request_id LEFT JOIN admins a ON a.id = e.admin_id
     ORDER BY e.id DESC LIMIT 8'
);

// ---- Getting started checklist ----
$checklist = [
    ['Name your brand', setting('brand_name') !== SITE_DEFAULTS['brand_name'], url('admin/settings.php'), 'Your business name, tagline and headline.'],
    ['Add your contact details', setting('contact_email') !== '' || setting('contact_phone') !== '', url('admin/settings.php') . '#contact', 'Email and phone shown on the website.'],
    ['Connect WhatsApp or Messenger', chat_link() !== null, url('admin/settings.php') . '#contact', 'Adds a "Chat with us" button on every page.'],
    ['Check your prices and FAQs', (bool) db_value("SELECT 1 FROM packages WHERE updated_at > created_at LIMIT 1") || (bool) db_value("SELECT 1 FROM faqs WHERE updated_at > created_at LIMIT 1"), url('admin/packages.php'), 'Make sure they match how you work.'],
    ['Add your first real project', (bool) db_value('SELECT 1 FROM projects WHERE is_concept = 0 AND is_active = 1 LIMIT 1'), url('admin/portfolio.php'), 'Replace the design concepts with client work.'],
    ['Add a client review', (bool) db_value('SELECT 1 FROM testimonials WHERE is_active = 1 LIMIT 1'), url('admin/testimonials.php'), 'Real reviews build trust fast.'],
];
$done = count(array_filter($checklist, fn($c) => $c[1]));

$hour = (int) $now->format('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

/** Column chart of requests per week (server-drawn SVG, hover/focus tooltips from admin.js). */
function weekly_chart(array $weeks): string
{
    $w = 640; $h = 230; $left = 34; $right = 8; $top = 26; $bottom = 30;
    $plotW = $w - $left - $right;
    $plotH = $h - $top - $bottom;
    $max = max(array_column($weeks, 'count'));
    $step = max(1, (int) ceil(max($max, 4) / 4));
    $yMax = $step * 4;
    $band = $plotW / count($weeks);
    $barW = min(24, $band * 0.55);
    $svg = '<svg class="chart-svg" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="Column chart of website requests per week for the last 12 weeks">';
    for ($t = 0; $t <= 4; $t++) {
        $y = $top + $plotH - ($t * $step / $yMax) * $plotH;
        $svg .= '<line class="grid" x1="' . $left . '" x2="' . ($w - $right) . '" y1="' . $y . '" y2="' . $y . '"/>';
        $svg .= '<text class="tick" x="' . ($left - 8) . '" y="' . ($y + 4) . '" text-anchor="end">' . ($t * $step) . '</text>';
    }
    $i = 0;
    $n = count($weeks);
    foreach ($weeks as $week) {
        $cx = $left + $band * $i + $band / 2;
        $x = $cx - $barW / 2;
        $current = $i === $n - 1;
        $label = $week['start']->format('M j');
        $tip = ($current ? 'This week' : 'Week of ' . $label) . ': ' . plural($week['count'], 'request');
        if ($week['count'] > 0) {
            $bh = max(4, ($week['count'] / $yMax) * $plotH);
            $y = $top + $plotH - $bh;
            $r = min(4, $bh / 2);
            // Rounded at the top (data end), square at the baseline.
            $d = sprintf('M%.1f %.1f V%.1f Q%.1f %.1f %.1f %.1f H%.1f Q%.1f %.1f %.1f %.1f V%.1f Z',
                $x, $top + $plotH, $y + $r, $x, $y, $x + $r, $y, $x + $barW - $r, $x + $barW, $y, $x + $barW, $y + $r, $top + $plotH);
            $svg .= '<path class="bar' . ($current ? ' bar-now' : '') . '" d="' . $d . '"/>';
            if ($current) {
                $svg .= '<text class="val" x="' . $cx . '" y="' . ($y - 7) . '" text-anchor="middle">' . $week['count'] . '</text>';
            }
        }
        if ($i % 2 === 1 || $current) {
            $svg .= '<text class="tick" x="' . $cx . '" y="' . ($h - 8) . '" text-anchor="middle">' . e($current ? 'Now' : $label) . '</text>';
        }
        // Hit area: the whole column, bigger than the bar, also reachable by keyboard.
        $svg .= '<rect class="hit" x="' . ($left + $band * $i) . '" y="' . $top . '" width="' . $band . '" height="' . $plotH . '" tabindex="0" data-tip="' . e($tip) . '"><title>' . e($tip) . '</title></rect>';
        $i++;
    }
    $svg .= '<line class="axis" x1="' . $left . '" x2="' . ($w - $right) . '" y1="' . ($top + $plotH) . '" y2="' . ($top + $plotH) . '"/>';
    return $svg . '</svg>';
}

admin_start('Dashboard', 'dashboard', '<a class="btn btn-primary" href="' . e(url('admin/requests.php')) . '">' . icon('inbox') . ' View requests</a>');
?>
<section class="welcome">
  <div>
    <p class="welcome-date"><?= e($now->format('l, F j')) ?></p>
    <h2><?= e($greeting) ?>, <?= e(first_name($admin['name'])) ?> 👋</h2>
    <p class="muted"><?php if ($byStatus['new'] > 0): ?>You have <b><?= plural($byStatus['new'], 'new request') ?></b> waiting for a reply.<?php else: ?>You're all caught up — no new requests waiting.<?php endif ?></p>
  </div>
  <div class="welcome-actions">
    <a class="btn btn-ghost" href="<?= e(url('admin/packages.php')) ?>"><?= icon('tag') ?> Edit pricing</a>
    <a class="btn btn-ghost" href="<?= e(url('admin/settings.php')) ?>"><?= icon('sliders') ?> Site settings</a>
  </div>
</section>

<section class="stats">
  <a class="stat stat-orange" href="<?= e(url('admin/requests.php', ['status' => 'new'])) ?>">
    <span class="stat-icon"><?= icon('inbox') ?></span>
    <span class="stat-label">New requests</span>
    <b class="stat-value"><?= number_format($byStatus['new']) ?></b>
    <small class="<?= $waiting ? 'warn' : '' ?>"><?= $waiting ? icon('clock') . ' ' . plural($waiting, 'waiting', 'waiting') . ' over 24 hours' : ($byStatus['new'] ? 'All less than a day old' : 'Nothing waiting') ?></small>
  </a>
  <a class="stat" href="<?= e(url('admin/requests.php', ['status' => 'quoted'])) ?>">
    <span class="stat-icon"><?= icon('chat') ?></span>
    <span class="stat-label">In conversation</span>
    <b class="stat-value"><?= number_format($byStatus['contacted'] + $byStatus['quoted']) ?></b>
    <small><?= $quotedValue > 0 ? e(money($quotedValue)) . ' quoted and open' : 'Contacted or quote sent' ?></small>
  </a>
  <a class="stat" href="<?= e(url('admin/requests.php', ['status' => 'in_progress'])) ?>">
    <span class="stat-icon"><?= icon('code') ?></span>
    <span class="stat-label">In progress</span>
    <b class="stat-value"><?= number_format($byStatus['in_progress']) ?></b>
    <small>Websites being built</small>
  </a>
  <a class="stat stat-green" href="<?= e(url('admin/requests.php', ['status' => 'launched'])) ?>">
    <span class="stat-icon"><?= icon('rocket') ?></span>
    <span class="stat-label">Launched</span>
    <b class="stat-value"><?= number_format($byStatus['launched']) ?></b>
    <small><?= $launchedThisMonth ?> this month<?= $wonValue > 0 ? ' · ' . e(money($wonValue)) . ' total' : '' ?></small>
  </a>
</section>

<?php if ($done < count($checklist)): ?>
<section class="card checklist">
  <div class="card-head">
    <div>
      <h2>Finish setting up your website</h2>
      <p class="muted"><?= $done ?> of <?= count($checklist) ?> done — each one makes your website more trustworthy.</p>
    </div>
    <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="<?= count($checklist) ?>" aria-valuenow="<?= $done ?>"><span style="width:<?= round($done / count($checklist) * 100) ?>%"></span></div>
  </div>
  <ul class="check-grid">
    <?php foreach ($checklist as [$label, $ok, $href, $help]): ?>
      <li class="<?= $ok ? 'is-done' : '' ?>">
        <a href="<?= e($href) ?>">
          <span class="check-dot"><?= $ok ? icon('check') : '' ?></span>
          <span><b><?= e($label) ?></b><small><?= e($help) ?></small></span>
        </a>
      </li>
    <?php endforeach ?>
  </ul>
</section>
<?php endif ?>

<div class="dash-grid">
  <section class="card chart-card">
    <div class="card-head">
      <div>
        <h2>Requests per week</h2>
        <p class="muted"><?= plural($last12, 'request') ?> in the last 12 weeks · this week in orange</p>
      </div>
    </div>
    <div class="chart" data-chart>
      <?= weekly_chart($weeks) ?>
      <div class="chart-tip" data-chart-tip hidden></div>
    </div>
    <details class="table-view">
      <summary>Show as table</summary>
      <table class="table table-compact">
        <thead><tr><th>Week starting</th><th class="num">Requests</th></tr></thead>
        <tbody>
          <?php foreach (array_reverse($weeks) as $wk): ?><tr><td><?= e($wk['start']->format('M j, Y')) ?></td><td class="num"><?= $wk['count'] ?></td></tr><?php endforeach ?>
        </tbody>
      </table>
    </details>
  </section>

  <section class="card">
    <div class="card-head"><div><h2>Pipeline</h2><p class="muted"><?= plural($total, 'request') ?> in total</p></div></div>
    <ul class="pipeline">
      <?php $pmax = max(1, max($byStatus));
      foreach (STATUSES as $key => $s): ?>
        <li>
          <a href="<?= e(url('admin/requests.php', ['status' => $key])) ?>">
            <span class="pl-label"><span class="dot dot-<?= e($s['tone']) ?>"></span><?= e($s['label']) ?></span>
            <span class="pl-bar"><span style="width:<?= $byStatus[$key] ? max(3, round($byStatus[$key] / $pmax * 100)) : 0 ?>%"></span></span>
            <b class="pl-count"><?= $byStatus[$key] ?></b>
          </a>
        </li>
      <?php endforeach ?>
    </ul>
  </section>
</div>

<div class="dash-grid">
  <section class="card">
    <div class="card-head">
      <div><h2>Needs a reply</h2><p class="muted">Oldest first — a quick reply wins more projects.</p></div>
      <?php if ($needsReply): ?><a class="link" href="<?= e(url('admin/requests.php', ['status' => 'new'])) ?>">See all</a><?php endif ?>
    </div>
    <?php if (!$needsReply): ?>
      <div class="empty-mini"><?= icon('check-circle') ?><p>Nothing waiting. Nice work!</p></div>
    <?php else: ?>
      <ul class="mini-list">
        <?php foreach ($needsReply as $r): $late = strtotime($r['created_at'] . ' UTC') < time() - 86400; ?>
          <li>
            <a href="<?= e(url('admin/request.php', ['id' => $r['id']])) ?>">
              <span class="avatar avatar-sm"><?= e(initials($r['name'])) ?></span>
              <span class="ml-text"><b><?= e($r['name']) ?></b><small><?= e($r['business_name'] ?: ($r['package_name'] ?: 'Website request')) ?> · <?= e($r['ref']) ?></small></span>
              <span class="ml-time<?= $late ? ' late' : '' ?>"><?= $late ? icon('clock') . ' ' : '' ?><?= e(time_ago($r['created_at'])) ?></span>
            </a>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </section>

  <section class="card">
    <div class="card-head"><div><h2>Recent activity</h2><p class="muted">The latest across all requests.</p></div></div>
    <?php if (!$activity): ?>
      <div class="empty-mini"><?= icon('activity') ?><p>When requests come in from your website, you'll see them here.</p></div>
    <?php else: ?>
      <ul class="feed">
        <?php foreach ($activity as $a): ?>
          <li>
            <span class="feed-icon feed-<?= e($a['kind']) ?>"><?= icon(['created' => 'inbox', 'status' => 'activity', 'note' => 'note', 'quote' => 'tag', 'client' => 'send'][$a['kind']] ?? 'activity') ?></span>
            <div>
              <p><a href="<?= e(url('admin/request.php', ['id' => $a['request_id']])) ?>"><b><?= e($a['name']) ?></b></a> · <?= e(mb_strimwidth($a['body'], 0, 90, '…')) ?></p>
              <small><?= e(time_ago($a['created_at'])) ?><?= $a['admin_name'] ? ' · by ' . e($a['admin_name']) : '' ?></small>
            </div>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </section>
</div>
<?php admin_end();
