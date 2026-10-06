<?php
/* Admin → Packages: create the promo packages customers can book, and upload photos. */
require dirname(__DIR__) . '/includes/bootstrap.php';

$admin = require_admin();
$self = url('admin/packages.php');
$editId = int_param('edit', 0, 0, PHP_INT_MAX);
$editing = $editId ? find_package_by_id($editId) : null;
if ($editId && !$editing) not_found();
$showForm = $editing || isset($_GET['new']);
$errors = [];
$v = $editing ? [
    'title' => $editing['title'], 'from' => $editing['from_code'], 'to' => $editing['to_code'], 'nights' => (string) $editing['nights'],
    'hotel' => $editing['hotel'], 'stars' => (string) ($editing['stars'] ?? ''), 'car' => $editing['car'], 'highlights' => implode("\n", $editing['highlights']),
    'price' => (string) $editing['price'], 'was_price' => (string) ($editing['was_price'] ?? ''), 'badge' => (string) $editing['badge'],
    'valid_from' => (string) $editing['valid_from'], 'valid_to' => (string) $editing['valid_to'], 'active' => $editing['active'], 'sort' => (string) $editing['sort'],
] : ['title' => '', 'from' => 'MNL', 'to' => '', 'nights' => '5', 'hotel' => '', 'stars' => '', 'car' => false, 'highlights' => '', 'price' => '', 'was_price' => '', 'badge' => '', 'valid_from' => '', 'valid_to' => '', 'active' => true, 'sort' => '0'];

if (is_post()) {
    verify_csrf();
    $action = post('action');
    if ($action === 'save') {
        $v = [
            'title' => post('title'), 'from' => strtoupper(post('from')), 'to' => strtoupper(post('to')), 'nights' => post('nights'),
            'hotel' => post('hotel'), 'stars' => post('stars'), 'car' => !empty($_POST['car']), 'highlights' => trim((string) ($_POST['highlights'] ?? '')),
            'price' => post('price'), 'was_price' => post('was_price'), 'badge' => post('badge'),
            'valid_from' => post('valid_from'), 'valid_to' => post('valid_to'), 'active' => !empty($_POST['active']), 'sort' => post('sort') ?: '0',
        ];
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $v['highlights']))));
        if (mb_strlen($v['title']) < 3 || mb_strlen($v['title']) > 120) $errors['title'] = 'Give the package a title (3–120 characters).';
        if (!airport($v['from'])) $errors['from'] = 'Choose the departure city from the list.';
        if (!airport($v['to'])) $errors['to'] = 'Choose the destination from the list.';
        elseif ($v['to'] === $v['from']) $errors['to'] = 'The destination must be different from the departure city.';
        if (!ctype_digit($v['nights']) || (int) $v['nights'] < 1 || (int) $v['nights'] > 60) $errors['nights'] = 'Enter 1 to 60 nights.';
        if (mb_strlen($v['hotel']) < 2 || mb_strlen($v['hotel']) > 120) $errors['hotel'] = 'Enter the hotel name.';
        if ($v['stars'] !== '' && !in_array($v['stars'], ['1', '2', '3', '4', '5'], true)) $errors['stars'] = 'Choose 1 to 5 stars, or leave empty.';
        if (count($lines) > 8 || array_filter($lines, fn($l) => mb_strlen($l) > 80)) $errors['highlights'] = 'Up to 8 lines, each up to 80 characters.';
        if (!ctype_digit($v['price']) || (int) $v['price'] < 1 || (int) $v['price'] > 100000) $errors['price'] = 'Enter the price per person in whole US dollars, e.g. 899.';
        if ($v['was_price'] !== '' && (!ctype_digit($v['was_price']) || (int) $v['was_price'] <= (int) $v['price'])) $errors['was_price'] = 'Must be higher than the price — or leave empty.';
        if (mb_strlen($v['badge']) > 30) $errors['badge'] = 'Keep the label short (up to 30 characters).';
        $from = $v['valid_from'] === '' ? null : date_param($v['valid_from'], '2000-01-01');
        $to = $v['valid_to'] === '' ? null : date_param($v['valid_to'], '2000-01-01');
        if ($v['valid_from'] !== '' && !$from) $errors['valid_from'] = 'Enter a valid date.';
        if ($v['valid_to'] !== '' && !$to) $errors['valid_to'] = 'Enter a valid date.';
        elseif ($from && $to && $to < $from) $errors['valid_to'] = 'The last date must be after the first date.';
        if (!preg_match('/^-?\d{1,4}$/', $v['sort'])) $errors['sort'] = 'Enter a whole number.';
        [$photo, $photoError] = $errors ? [null, null] : save_photo($_FILES['photo'] ?? []);
        if ($photoError) $errors['photo'] = $photoError;

        if (!$errors) {
            $image = $editing['image'] ?? null;
            if ($photo || !empty($_POST['remove_photo'])) {
                delete_photo($image);
                $image = $photo;
            }
            $fields = [
                $v['title'], $v['from'], $v['to'], (int) $v['nights'], $v['hotel'], $v['stars'] === '' ? null : (int) $v['stars'], (int) $v['car'],
                implode("\n", $lines), (int) $v['price'], $v['was_price'] === '' ? null : (int) $v['was_price'], $v['badge'] === '' ? null : $v['badge'],
                $from, $to, $image, (int) $v['active'], (int) $v['sort'], now_utc(),
            ];
            $cols = 'title = ?, from_code = ?, to_code = ?, nights = ?, hotel = ?, stars = ?, includes_car = ?, highlights = ?, price = ?, was_price = ?, badge = ?, valid_from = ?, valid_to = ?, image = ?, active = ?, sort = ?, updated_at = ?';
            if ($editing) {
                db_run("UPDATE packages SET $cols WHERE id = ?", [...$fields, $editing['id']]);
            } else {
                db_run("INSERT INTO packages SET $cols, slug = ?, created_at = ?", [...$fields, package_slug($v['title']), now_utc()]);
            }
            flash($editing ? 'Package saved.' : 'Package created.' . ($v['active'] ? ' It is now on your website.' : ' It is switched off — customers can\'t see it yet.'));
            redirect($self);
        }
    } elseif ($action === 'toggle' && ($p = find_package_by_id((int) post('id')))) {
        db_run('UPDATE packages SET active = ?, updated_at = ? WHERE id = ?', [(int) !$p['active'], now_utc(), $p['id']]);
        flash($p['active'] ? "\"{$p['title']}\" is now hidden from customers." : "\"{$p['title']}\" is now on your website.");
        redirect($self);
    } elseif ($action === 'delete' && ($p = find_package_by_id((int) post('id')))) {
        db_run('DELETE FROM packages WHERE id = ?', [$p['id']]);
        delete_photo($p['image']);
        flash("\"{$p['title']}\" deleted. Bookings already made for it are not affected.");
        redirect($self);
    } elseif ($action === 'dest_photo') {
        $code = (string) post('code');
        if (!in_array($code, array_column(POPULAR_DESTINATIONS, 2), true)) not_found();
        if (!empty($_POST['remove'])) {
            set_site_image("dest:$code", null);
            flash('Photo removed.');
            redirect($self . '#destinations');
        }
        [$photo, $photoError] = save_photo($_FILES['photo'] ?? []);
        if ($photoError || !$photo) {
            $errors["dest_$code"] = $photoError ?? 'Choose a photo first.';
        } else {
            set_site_image("dest:$code", $photo);
            flash('Photo saved — it now shows on your homepage.');
            redirect($self . '#destinations');
        }
    }
}

$packages = all_packages();
$title = 'Packages';
$noindex = true;
require dirname(__DIR__) . '/includes/header.php';
echo admin_open('packages');
$input = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900';
$err = fn(string $k) => isset($errors[$k]) ? '<p class="mt-1 text-sm text-red-600">' . e($errors[$k]) . '</p>' : '';
$label = 'block text-sm font-medium text-slate-700';
?>
<div class="space-y-10">
  <?php if ($showForm): ?>
    <form method="post" enctype="multipart/form-data" class="max-w-3xl space-y-6" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="save">
      <div>
        <nav class="mb-2 text-sm text-slate-500"><a href="<?= e($self) ?>" class="hover:text-brand-700">← All packages</a></nav>
        <h1 class="text-2xl font-bold text-slate-900"><?= $editing ? 'Edit package' : 'New package' ?></h1>
        <p class="text-slate-600">Everything here is shown to customers exactly as you write it. Only promise what's really included.</p>
      </div>
      <?php if ($errors): ?><?= alert_box('Please fix the highlighted fields.') ?><?php endif; ?>

      <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900">Trip</h2>
        <div><label for="p_title" class="<?= $label ?>">Package title</label>
          <input id="p_title" name="title" maxlength="120" value="<?= e($v['title']) ?>" placeholder="e.g. Boracay Beach Escape" class="<?= $input ?> mt-1"><?= $err('title') ?></div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div><?= airport_field('from', 'Flights from', 'City or airport', $v['from']) ?><?= $err('from') ?></div>
          <div><?= airport_field('to', 'Destination', 'City or airport', $v['to']) ?><?= $err('to') ?></div>
        </div>
        <div class="grid gap-4 sm:grid-cols-[1fr_2fr_1fr]">
          <div><label for="p_nights" class="<?= $label ?>">Nights</label>
            <input id="p_nights" name="nights" type="number" min="1" max="60" value="<?= e($v['nights']) ?>" class="<?= $input ?> mt-1"><?= $err('nights') ?></div>
          <div><label for="p_hotel" class="<?= $label ?>">Hotel</label>
            <input id="p_hotel" name="hotel" maxlength="120" value="<?= e($v['hotel']) ?>" placeholder="Exact hotel name" class="<?= $input ?> mt-1"><?= $err('hotel') ?></div>
          <div><label for="p_stars" class="<?= $label ?>">Hotel stars</label>
            <select id="p_stars" name="stars" class="<?= $input ?> mt-1"><option value="">Don't show</option><?php for ($n = 1; $n <= 5; $n++): ?><option value="<?= $n ?>"<?= $v['stars'] === (string) $n ? ' selected' : '' ?>><?= $n ?> ★</option><?php endfor; ?></select><?= $err('stars') ?></div>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="car" value="1"<?= $v['car'] ? ' checked' : '' ?> class="h-4 w-4 accent-brand-600"> Includes a rental car</label>
        <div><label for="p_highlights" class="<?= $label ?>">What's included <span class="font-normal text-slate-400">· one per line, up to 8</span></label>
          <textarea id="p_highlights" name="highlights" rows="4" class="<?= $input ?> mt-1" placeholder="Daily breakfast&#10;Airport transfers&#10;Island hopping tour"><?= e($v['highlights']) ?></textarea>
          <p class="mt-1 text-xs text-slate-500">Round-trip flights and the hotel are always listed. The first 4 lines show on the card.</p><?= $err('highlights') ?></div>
      </section>

      <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900">Price &amp; dates</h2>
        <div class="grid gap-4 sm:grid-cols-3">
          <div><label for="p_price" class="<?= $label ?>">Price per person (USD)</label>
            <input id="p_price" name="price" type="number" min="1" step="1" value="<?= e($v['price']) ?>" placeholder="899" class="<?= $input ?> mt-1"><?= $err('price') ?></div>
          <div><label for="p_was" class="<?= $label ?>">"Was" price <span class="font-normal text-slate-400">· optional</span></label>
            <input id="p_was" name="was_price" type="number" min="1" step="1" value="<?= e($v['was_price']) ?>" class="<?= $input ?> mt-1"><?= $err('was_price') ?></div>
          <div><label for="p_badge" class="<?= $label ?>">Label <span class="font-normal text-slate-400">· optional</span></label>
            <input id="p_badge" name="badge" maxlength="30" value="<?= e($v['badge']) ?>" placeholder="e.g. Limited time" class="<?= $input ?> mt-1"><?= $err('badge') ?></div>
        </div>
        <p class="text-xs text-slate-500">The price is the total per person, including taxes. Member discounts don't apply to packages. Only fill in a "was" price if you really sold it at that price — it shows a crossed-out price and "You save". Make sure the price covers the flights, hotel, card fees and your profit, because supplier prices can change before you buy.</p>
        <div class="grid gap-4 sm:grid-cols-2">
          <div><label for="p_from" class="<?= $label ?>">First departure date <span class="font-normal text-slate-400">· optional</span></label>
            <input id="p_from" name="valid_from" type="date" min="2020-01-01" max="2099-12-31" value="<?= e($v['valid_from']) ?>" class="<?= $input ?> mt-1"><?= $err('valid_from') ?></div>
          <div><label for="p_to" class="<?= $label ?>">Last departure date <span class="font-normal text-slate-400">· optional</span></label>
            <input id="p_to" name="valid_to" type="date" min="2020-01-01" max="2099-12-31" value="<?= e($v['valid_to']) ?>" class="<?= $input ?> mt-1"><?= $err('valid_to') ?></div>
        </div>
        <p class="text-xs text-slate-500">Customers choose a departure date between these. After the last date the package disappears from the website automatically.</p>
      </section>

      <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-slate-900">Photo</h2>
        <?php if ($editing && ($url = upload_url($editing['image']))): ?>
          <img src="<?= e($url) ?>" alt="" class="h-40 w-full max-w-sm rounded-lg object-cover">
          <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="remove_photo" value="1" class="h-4 w-4 accent-red-600"> Remove this photo</label>
        <?php endif; ?>
        <div><label for="p_photo" class="<?= $label ?>"><?= $editing && $editing['image'] ? 'Replace photo' : 'Upload a photo' ?></label>
          <input id="p_photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm text-slate-700 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:font-semibold file:text-brand-700">
          <p class="mt-1 text-xs text-slate-500">JPG, PNG or WebP, at least 800 pixels wide, up to 8 MB. Landscape photos look best. Use your own photos or ones you have permission to use (e.g. from the hotel, or free sites like Unsplash or Pexels).</p><?= $err('photo') ?></div>
      </section>

      <section class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-6">
        <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input type="checkbox" name="active" value="1"<?= $v['active'] ? ' checked' : '' ?> class="h-4 w-4 accent-brand-600"> Show on the website</label>
        <label class="flex items-center gap-2 text-sm text-slate-700">Order <input name="sort" type="number" value="<?= e($v['sort']) ?>" class="w-20 rounded-lg border border-slate-300 px-2 py-1 text-sm"><span class="text-xs text-slate-500">lower = first</span></label>
        <?= $err('sort') ?>
      </section>

      <div class="flex gap-3">
        <button type="submit" class="rounded-xl bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700"><?= $editing ? 'Save package' : 'Create package' ?></button>
        <a href="<?= e($self) ?>" class="rounded-xl px-6 py-3 font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">Cancel</a>
      </div>
    </form>
  <?php else: ?>
    <section>
      <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div><h1 class="text-2xl font-bold text-slate-900">Packages</h1><p class="text-slate-600">Your promo packages. Customers book them at the price you set.</p></div>
        <a href="<?= e(url('admin/packages.php', ['new' => 1])) ?>" class="rounded-xl bg-brand-600 px-5 py-2.5 font-semibold text-white hover:bg-brand-700">+ New package</a>
      </div>
      <?php if (!$packages): ?>
        <div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-600">No packages yet. The promo section is hidden on your homepage until you add one.</div>
      <?php else: ?>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
          <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Package</th><th class="px-4 py-3">Route</th><th class="px-4 py-3">Travel dates</th><th class="px-4 py-3 text-right">Price</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($packages as $p): [$first, $last] = package_dates($p); $ended = $first > $last; ?>
                <tr>
                  <td class="px-4 py-3"><div class="flex items-center gap-3">
                    <?php if ($url = upload_url($p['image'])): ?><img src="<?= e($url) ?>" alt="" class="h-10 w-14 rounded object-cover"><?php else: ?><span class="h-10 w-14 rounded bg-gradient-to-br <?= $p['gradient'] ?>"></span><?php endif; ?>
                    <div><p class="font-semibold text-slate-900"><?= e($p['title']) ?></p><p class="text-xs text-slate-500"><?= $p['nights'] ?> nights · <?= e($p['hotel']) ?></p></div></div></td>
                  <td class="px-4 py-3 text-slate-700"><?= e($p['from_code']) ?> → <?= e($p['to_code']) ?></td>
                  <td class="px-4 py-3 text-slate-700"><?= $ended ? '<span class="text-red-600">Ended</span>' : e(fmt_date($first)) . ' – ' . e(fmt_date($last)) ?></td>
                  <td class="px-4 py-3 text-right font-semibold text-slate-900"><?= money($p['price']) ?></td>
                  <td class="px-4 py-3"><?= $p['active'] && !$ended ? '<span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">On website</span>' : '<span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-bold text-slate-600">Hidden</span>' ?></td>
                  <td class="px-4 py-3"><div class="flex justify-end gap-2">
                    <a href="<?= e(url('admin/packages.php', ['edit' => $p['id']])) ?>" class="rounded-lg px-3 py-1.5 font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50">Edit</a>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                      <button type="submit" class="rounded-lg px-3 py-1.5 font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50"><?= $p['active'] ? 'Hide' : 'Show' ?></button></form>
                    <form method="post" data-confirm="Delete &quot;<?= e($p['title']) ?>&quot;? Bookings already made for it are kept."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                      <button type="submit" class="rounded-lg px-3 py-1.5 font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50">Delete</button></form>
                  </div></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section id="destinations" class="scroll-mt-24">
      <h2 class="text-lg font-semibold text-slate-900">Homepage destination photos</h2>
      <p class="mb-4 text-sm text-slate-600">The "Popular destinations" cards on your homepage. Each has a free default photo from Unsplash; upload your own to replace it.</p>
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach (POPULAR_DESTINATIONS as [$city, $country, $code, $grad, $unsplash]): [$shown, $isDefault] = destination_photo($code, $unsplash); $photo = $isDefault ? null : $shown; ?>
          <form method="post" enctype="multipart/form-data" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <?= csrf_field() ?><input type="hidden" name="action" value="dest_photo"><input type="hidden" name="code" value="<?= e($code) ?>">
            <div class="relative h-32 bg-gradient-to-br <?= $grad ?>"><img src="<?= e($shown) ?>" alt="" loading="lazy" class="h-full w-full object-cover">
              <span class="absolute bottom-2 left-3 font-bold text-white drop-shadow"><?= e($city) ?></span>
              <span class="absolute right-2 top-2 rounded bg-black/50 px-2 py-0.5 text-xs font-semibold text-white"><?= $isDefault ? 'Default photo' : 'Your photo' ?></span></div>
            <div class="space-y-2 p-4">
              <input name="photo" type="file" accept="image/jpeg,image/png,image/webp" aria-label="Photo for <?= e($city) ?>" class="block w-full text-xs text-slate-700 file:mr-2 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:font-semibold file:text-brand-700">
              <?= $err("dest_$code") ?>
              <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700">Upload</button>
                <?php if ($photo): ?><button type="submit" name="remove" value="1" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-red-600 ring-1 ring-red-200 hover:bg-red-50">Remove</button><?php endif; ?>
              </div>
            </div>
          </form>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>
<?php echo admin_close(); require dirname(__DIR__) . '/includes/footer.php';
