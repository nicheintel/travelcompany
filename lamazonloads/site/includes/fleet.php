<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/* The fleet: branded drawings of each vehicle, the animated "Meet the fleet" showroom,
   and Site photos (real pictures uploaded in Admin that replace the drawings). */

const FLEET = [
    'cargo_van' => [
        'name' => 'Cargo Van',
        'icon' => 'van',
        'tag' => 'Quick, nimble and perfect for expedited and multi-stop freight.',
        'specs' => [['Typical cargo', 'Up to 2 pallets'], ['Typical payload', '2,500–3,500 lbs'], ['Great for', 'Expedited & same-day']],
        'uses' => ['Expedited and same-day freight', 'Last-mile and multi-stop routes', 'Parcels, auto parts and pharmacy runs'],
    ],
    'sprinter' => [
        'name' => 'Sprinter Van',
        'icon' => 'van',
        'tag' => 'High roof and a long body: more room, and still no CDL needed.',
        'specs' => [['Typical cargo', 'Up to 3–4 pallets'], ['Typical payload', '3,000–5,000 lbs'], ['Great for', 'Expedited & partials']],
        'uses' => ['Expedited and partial loads', 'Dedicated daily routes', 'Healthcare and medical supply runs'],
    ],
    'box_truck' => [
        'name' => 'Box Truck',
        'icon' => 'truck',
        'tag' => '16 to 26 ft straight trucks for the big stuff, no CDL needed on most.',
        'specs' => [['Typical cargo', '6–12 pallets'], ['Typical payload', 'Up to ~10,000 lbs'], ['Great for', 'LTL & dedicated']],
        'uses' => ['LTL and partial truckloads', 'Furniture and appliance delivery', 'Dedicated and contract routes'],
    ],
];

const PHOTO_SLOTS = ['cargo_van' => 'Cargo Van', 'sprinter' => 'Sprinter Van', 'box_truck' => 'Box Truck', 'gallery' => 'On the road (gallery)'];
const GALLERY_MAX = 12;

/** Every stock photo on the website: spot => [group, name in Admin, original photo in assets/photos, where it shows]. */
const PAGE_PHOTOS = [
    'home_hero'           => ['Home page', 'Top banner', 'home-sprinter-courier', 'Behind “Why wait? Let’s freight.” and the dispatch desk'],
    'home_mosaic_1'       => ['Home page', 'Photo grid: large photo', 'driver-van-window', '“Support on every route”'],
    'home_mosaic_2'       => ['Home page', 'Photo grid: 2', 'van-sorting', '“Loads that fit your van”'],
    'home_mosaic_3'       => ['Home page', 'Photo grid: 3', 'woman-courier', '“Drivers of every kind”'],
    'home_mosaic_4'       => ['Home page', 'Photo grid: 4', 'truck-driver-cab', '“Box trucks welcome”'],
    'home_mosaic_5'       => ['Home page', 'Photo grid: 5', 'doorstep-handoff', '“Delivered with care”'],
    'banner_services'     => ['Page banners', 'Services', 'van-loaded', 'Top of the Services page'],
    'banner_drivers'      => ['Page banners', 'Drive with us', 'driver-van-window', 'Top of the Drive with us page'],
    'banner_careers'      => ['Page banners', 'Careers', 'courier-smile', 'Top of the Careers page'],
    'banner_about'        => ['Page banners', 'About', 'driver-wheel', 'Top of the About page'],
    'banner_contact'      => ['Page banners', 'Contact', 'support-agent', 'Top of the Contact page'],
    'drivers_band_1'      => ['Drive with us & About', 'Drive with us: photo 1', 'van-loading', '“Onboard once”'],
    'drivers_band_2'      => ['Drive with us & About', 'Drive with us: photo 2', 'courier-smile', '“Get matched”'],
    'drivers_band_3'      => ['Drive with us & About', 'Drive with us: photo 3', 'truck-driver-cab', '“Stay loaded”'],
    'about_band_1'        => ['Drive with us & About', 'About: photo 1', 'truck-driver-cab', '“Drivers first”'],
    'about_band_2'        => ['Drive with us & About', 'About: photo 2', 'warehouse-team', '“Organized logistics”'],
    'about_band_3'        => ['Drive with us & About', 'About: photo 3', 'support-agent', '“Real people on support”'],
    'partners_hero'       => ['Partners', 'Top banner', 'handshake', 'Behind “Your deliveries. Our network.”'],
    'partners_developers' => ['Partners', 'Developers photo', 'developers', 'Next to “Developers behind the scenes”'],
    'card_last_mile'      => ['Partners', 'Card: Last-Mile Delivery', 'van-loading', 'Header of the Last-Mile Delivery card'],
    'card_healthcare'     => ['Partners', 'Card: Healthcare', 'pharmacy-gloves', 'Header of the Healthcare card'],
    'card_dedicated'      => ['Partners', 'Card: Dedicated Fleet', 'warehouse-team', 'Header of the Dedicated Fleet card'],
    'hero_last_mile'      => ['Solution pages', 'Last-Mile: top banner', 'doorstep-handoff', 'Top of the Last-Mile Delivery page'],
    'overview_last_mile'  => ['Solution pages', 'Last-Mile: overview photo', 'van-sorting', 'Next to the Last-Mile overview'],
    'hero_healthcare'     => ['Solution pages', 'Healthcare: top banner', 'medical-supplies', 'Top of the Healthcare page'],
    'overview_healthcare' => ['Solution pages', 'Healthcare: overview photo', 'nurse-care', 'Next to the Healthcare overview'],
    'hero_dedicated'      => ['Solution pages', 'Dedicated Fleet: top banner', 'truck-driver-cab', 'Top of the Dedicated Fleet page'],
    'overview_dedicated'  => ['Solution pages', 'Dedicated Fleet: overview photo', 'van-unloading', 'Next to the Dedicated Fleet overview'],
];

/** [small URL, large URL, uploaded photo row or null, small file on disk] for a photo spot (or a stock photo name). */
function photo_urls(string $name): array
{
    $root = dirname(__DIR__);
    if (isset(PAGE_PHOTOS[$name])) {
        $up = site_photo($name);
        if ($up) {
            $small = preg_replace('/\.(webp|jpg)$/', '-800.$1', $up['file']);
            $small = is_file($root . '/media/' . $small) ? $small : $up['file'];
            return [media_url($small), media_url($up['file']), $up, $root . '/media/' . $small];
        }
        $name = PAGE_PHOTOS[$name][2];
    }
    return [asset('photos/' . $name . '-800.webp'), asset('photos/' . $name . '-1600.webp'), null, $root . '/assets/photos/' . $name . '-800.webp'];
}

/**
 * Tiny blurred copy of a photo (about 0.5 KB) put inline in the page, so a soft preview shows
 * instantly while the real photo loads. Made once per photo and kept in storage/lqip.
 */
function photo_placeholder(string $file): string
{
    if (!is_file($file) || !function_exists('imagecreatefromstring')) {
        return '';
    }
    $dir = dirname(__DIR__) . '/storage/lqip';
    $cache = $dir . '/' . md5($file . '|' . filemtime($file)) . '.txt';
    if (is_file($cache)) {
        return (string) file_get_contents($cache);
    }
    $img = @imagecreatefromstring((string) file_get_contents($file));
    if (!$img) {
        return '';
    }
    $tiny = imagescale($img, 24, max(1, (int) round(imagesy($img) * 24 / imagesx($img))), IMG_BILINEAR_FIXED);
    ob_start();
    function_exists('imagewebp') ? imagewebp($tiny, null, 45) : imagejpeg($tiny, null, 50);
    $uri = 'data:image/' . (function_exists('imagewebp') ? 'webp' : 'jpeg') . ';base64,' . base64_encode((string) ob_get_clean());
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($cache, $uri);
    return $uri;
}

/** One wheel, centred on (0, 0): placed with translate so CSS can spin the inner group. */
function vehicle_wheel(float $cx, float $cy): string
{
    $spokes = '';
    for ($i = 0; $i < 5; $i++) {
        $a = deg2rad($i * 72 - 90);
        $spokes .= '<path d="M0 0L' . round(cos($a) * 17, 1) . ' ' . round(sin($a) * 17, 1) . '"/>';
    }
    return '<path d="M' . ($cx - 41) . ' ' . $cy . 'A41 41 0 0 1 ' . ($cx + 41) . ' ' . $cy . 'Z" fill="#16213F"/>'
        . '<g transform="translate(' . $cx . ' ' . $cy . ')"><g class="v-wheel">'
        . '<circle r="34" fill="#111A33"/><circle r="27" fill="#24304F"/><circle r="20" fill="#E3E9F5"/>'
        . '<g stroke="#9DADCB" stroke-width="5" stroke-linecap="round">' . $spokes . '</g>'
        . '<circle r="7" fill="#5E6B85"/><circle r="2.5" fill="#E3E9F5"/></g></g>';
}

/** Side-view drawing of a LamazonLoads vehicle (cargo_van, sprinter or box_truck), facing right. */
function vehicle_svg(string $type, string $class = 'vehicle-svg'): string
{
    static $n = 0;
    $u = 'v' . (++$n);
    $phone = (string) config('contact_phone');
    $brand = fn (float $x, float $y, float $size, float $len): string => '<text x="' . $x . '" y="' . $y . '" font-family="Montserrat,Arial,sans-serif" font-weight="900" font-style="italic" font-size="' . $size . '" textLength="' . $len . '" lengthAdjust="spacingAndGlyphs" fill="#0A2463">Lamazon<tspan fill="#1E63E9">Loads</tspan></text>';
    $motto = fn (float $x, float $y, float $size): string => '<text x="' . $x . '" y="' . $y . '" font-family="Montserrat,Arial,sans-serif" font-weight="800" font-style="italic" font-size="' . $size . '" fill="#5E6B85">Why wait? <tspan fill="#1E63E9">Let’s freight.</tspan></text>';
    $defs = '<defs>'
        . '<linearGradient id="' . $u . 'b" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFFFFF"/><stop offset=".72" stop-color="#F1F4FA"/><stop offset="1" stop-color="#D5DDEE"/></linearGradient>'
        . '<linearGradient id="' . $u . 'g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#3D63B8"/><stop offset=".55" stop-color="#0F2A6B"/><stop offset="1" stop-color="#081A47"/></linearGradient>'
        . '<linearGradient id="' . $u . 'l" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#0A2463"/><stop offset=".55" stop-color="#1E63E9"/><stop offset="1" stop-color="#5AA2FF"/></linearGradient>'
        . '<radialGradient id="' . $u . 's" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="#030B22" stop-opacity=".45"/><stop offset="1" stop-color="#030B22" stop-opacity="0"/></radialGradient>'
        . '</defs>';
    $shadow = '<ellipse cx="320" cy="272" rx="300" ry="16" fill="url(#' . $u . 's)"/>';
    $glass = 'fill="url(#' . $u . 'g)"';

    if ($type === 'box_truck') {
        $box = 'M24 44Q24 32 36 32H416Q426 32 426 42V206H24Z';
        $cab = 'M434 98H508Q522 98 530 110L568 170L592 176Q606 180 606 196V228Q606 240 594 240H558A41 41 0 0 0 476 240H434Z';
        $body = '<path d="' . $box . '" fill="url(#' . $u . 'b)" stroke="#C9D3E8" stroke-width="2"/>'
            . '<clipPath id="' . $u . 'c"><path d="' . $box . '"/></clipPath>'
            . '<g clip-path="url(#' . $u . 'c)"><path d="M24 168C150 152 290 196 426 160V206H24Z" fill="url(#' . $u . 'l)"/>'
            . '<path d="M24 160C150 144 290 188 426 152" fill="none" stroke="#8EC2FF" stroke-width="3"/></g>'
            . $brand(56, 112, 66, 336) . $motto(60, 146, 21)
            . '<text x="60" y="194" font-family="Inter,Arial,sans-serif" font-weight="800" font-size="15" fill="#fff" letter-spacing=".5">lamazonloads.com</text>'
            . ($phone !== '' ? '<text x="404" y="196" text-anchor="end" font-family="Inter,Arial,sans-serif" font-weight="800" font-size="17" fill="#fff">' . e($phone) . '</text>' : '')
            . '<path d="M30 40V200" stroke="#C9D3E8" stroke-width="2"/><rect x="26" y="188" width="6" height="16" rx="2" fill="#E5484D"/>'
            . '<rect x="24" y="206" width="490" height="16" rx="3" fill="#1A2546"/>'
            . '<rect x="330" y="214" width="62" height="20" rx="8" fill="#C9D2E6"/><path d="M340 220h42" stroke="#9AA6C2" stroke-width="2"/>'
            . '<path d="M420 214h10v30h-10z" fill="#0B1733"/>'
            . '<path d="' . $cab . '" fill="url(#' . $u . 'b)" stroke="#C9D3E8" stroke-width="2"/>'
            . '<path d="M444 110H502Q510 110 514 116L548 166H444Z" ' . $glass . '/>'
            . '<path d="M440 104V232" stroke="#C9D3E8" stroke-width="2"/><rect x="452" y="176" width="16" height="5" rx="2" fill="#9AA6C2"/>'
            . '<rect x="546" y="148" width="8" height="24" rx="3" fill="#0A2463"/>'
            . '<path d="M592 182Q604 184 605 196H588Z" fill="#FFF6D5" class="v-light"/>'
            . '<rect x="572" y="226" width="40" height="16" rx="6" fill="#1A2546"/>'
            . '<path d="M440 196C500 190 560 196 606 206V214C560 206 500 204 440 210Z" fill="url(#' . $u . 'l)"/>'
            . vehicle_wheel(150, 240) . vehicle_wheel(517, 240);
    } else {
        $sprinter = $type === 'sprinter';
        $path = $sprinter
            ? 'M36 82Q36 52 66 50L418 48Q442 48 454 62L514 148Q520 156 532 158L574 164Q598 168 600 192L602 224Q602 236 590 236H540A42 42 0 0 0 456 236H188A42 42 0 0 0 104 236H48Q36 236 36 224Z'
            : 'M44 102Q44 84 62 84H404Q424 84 438 96L506 152Q514 158 526 160L570 166Q592 170 594 192L596 224Q596 236 584 236H512A42 42 0 0 0 428 236H196A42 42 0 0 0 112 236H56Q44 236 44 224Z';
        [$wa, $wb] = $sprinter ? [146, 498] : [154, 470];
        $body = '<path d="' . $path . '" fill="url(#' . $u . 'b)" stroke="#C9D3E8" stroke-width="2"/>'
            . '<clipPath id="' . $u . 'c"><path d="' . $path . '"/></clipPath>'
            . '<g clip-path="url(#' . $u . 'c)">'
            . '<path d="M30 200C180 186 340 222 500 196C540 190 572 192 610 198V216C570 210 530 212 480 220C330 244 180 214 30 224Z" fill="url(#' . $u . 'l)"/>'
            . '<path d="M30 194C180 180 340 216 500 190C540 184 572 186 610 192" fill="none" stroke="#8EC2FF" stroke-width="3"/>'
            . '<path d="M30 236H610V250H30Z" fill="#B8C4DD"/></g>'
            . ($sprinter
                ? $brand(92, 136, 54, 300) . $motto(96, 168, 17)
                    . '<path d="M432 72H446Q452 72 456 78L502 150H432Q426 150 426 144V78Q426 72 432 72Z" ' . $glass . '/>'
                    . '<path d="M418 60V228M272 56V228M44 60V226" stroke="#C9D3E8" stroke-width="2"/>'
                    . '<rect x="280" y="150" width="16" height="5" rx="2" fill="#9AA6C2"/><rect x="434" y="166" width="16" height="5" rx="2" fill="#9AA6C2"/>'
                    . '<rect x="506" y="140" width="9" height="20" rx="3" fill="#0A2463"/>'
                    . '<path d="M578 170Q594 172 596 188L576 186Z" fill="#FFF6D5" class="v-light"/>'
                    . '<rect x="584" y="192" width="14" height="20" rx="3" fill="#0A2463"/>'
                    . '<rect x="566" y="220" width="40" height="16" rx="6" fill="#1A2546"/><rect x="30" y="220" width="30" height="16" rx="6" fill="#1A2546"/>'
                    . '<rect x="36" y="96" width="6" height="44" rx="2" fill="#E5484D"/>'
                    . '<path d="M66 54H418" stroke="#fff" stroke-width="3" opacity=".8"/>'
                : $brand(100, 152, 44, 270) . $motto(104, 178, 15)
                    . '<path d="M414 100H432Q440 100 446 106L496 150H414Q408 150 408 144V106Q408 100 414 100Z" ' . $glass . '/>'
                    . '<path d="M402 96V228M252 90V228M52 92V226" stroke="#C9D3E8" stroke-width="2"/>'
                    . '<rect x="260" y="150" width="16" height="5" rx="2" fill="#9AA6C2"/><rect x="414" y="166" width="16" height="5" rx="2" fill="#9AA6C2"/>'
                    . '<rect x="500" y="142" width="9" height="18" rx="3" fill="#0A2463"/>'
                    . '<path d="M572 172Q588 174 590 188L570 186Z" fill="#FFF6D5" class="v-light"/>'
                    . '<rect x="580" y="194" width="12" height="18" rx="3" fill="#0A2463"/>'
                    . '<rect x="560" y="220" width="40" height="16" rx="6" fill="#1A2546"/><rect x="38" y="220" width="30" height="16" rx="6" fill="#1A2546"/>'
                    . '<rect x="44" y="110" width="6" height="40" rx="2" fill="#E5484D"/>'
                    . '<path d="M62 88H404" stroke="#fff" stroke-width="3" opacity=".8"/>')
            . vehicle_wheel($wa, 236) . vehicle_wheel($wb, 236);
    }
    return '<svg class="' . e($class) . '" viewBox="0 0 640 290" role="img" aria-label="LamazonLoads ' . e(FLEET[$type]['name'] ?? 'vehicle') . '">'
        . $defs . $shadow . '<g class="v-body">' . $body . '</g></svg>';
}

/** Uploaded photo for a vehicle slot, or null. */
function site_photo(string $slot): ?array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all("SELECT * FROM site_photos WHERE slot <> 'gallery' ORDER BY id") as $r) {
            $cache[$r['slot']] = $r;
        }
    }
    return $cache[$slot] ?? null;
}

function gallery_photos(): array
{
    return db_all("SELECT * FROM site_photos WHERE slot = 'gallery' ORDER BY sort, id DESC LIMIT " . GALLERY_MAX);
}

function media_url(string $file): string
{
    return url('media/' . rawurlencode($file));
}

/**
 * Saves an uploaded picture: checks it really is a JPG, PNG or WebP image, turns phone photos the right way up,
 * shrinks it to at most 1800 px wide and saves a fresh copy (nothing from the original file is kept but the picture).
 * Returns [file name, error].
 */
function save_site_photo(?array $f): array
{
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['', 'Please choose a photo to upload.'];
    }
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f['size'] > 12 * 1024 * 1024) {
        return ['', 'That photo is too big. The limit is 12 MB.'];
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return ['', 'The upload did not finish. Please try again.'];
    }
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $info = @getimagesize($f['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !$info || $info[0] < 200 || $info[1] < 150) {
        return ['', 'Please upload a JPG, PNG or WebP photo (at least 200 × 150 pixels).'];
    }
    if ($info[0] * $info[1] > 40_000_000) {
        return ['', 'That photo has too many pixels. Please use a smaller version.'];
    }
    if (!function_exists('imagecreatefromstring')) {
        return ['', 'Photo uploads need the PHP GD extension. In hPanel: Advanced → PHP Configuration → Extensions → turn on "gd".'];
    }
    $img = @imagecreatefromstring((string) file_get_contents($f['tmp_name']));
    if (!$img) {
        return ['', 'That photo could not be read. Please try another file.'];
    }
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $o = (int) ((@exif_read_data($f['tmp_name']) ?: [])['Orientation'] ?? 1);
        $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($rot) {
            $img = imagerotate($img, $rot, 0) ?: $img;
        }
    }
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w > 1800) {
        $img = imagescale($img, 1800, (int) round($h * 1800 / $w), IMG_BICUBIC) ?: $img;
    }
    $dir = dirname(__DIR__) . '/media';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $webp = function_exists('imagewebp');
    $name = bin2hex(random_bytes(12)) . ($webp ? '.webp' : '.jpg');
    if ($webp) {
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);
        $ok = imagewebp($img, $dir . '/' . $name, 82);
    } else {
        $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
        imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        $ok = imagejpeg($bg, $dir . '/' . $name, 85);
    }
    if (!$ok) {
        return ['', 'Could not save the photo. Please check that the media folder can be written to.'];
    }
    // Smaller copy for phones
    $w = imagesx($img);
    if ($w > 900) {
        $small = imagescale($img, 800, (int) round(imagesy($img) * 800 / $w), IMG_BICUBIC);
        if ($small) {
            $smallName = preg_replace('/\.(webp|jpg)$/', '-800.$1', $name);
            $webp ? imagewebp($small, $dir . '/' . $smallName, 80) : imagejpeg($small, $dir . '/' . $smallName, 85);
        }
    }
    return [$name, ''];
}

function delete_site_photo_file(string $file): void
{
    if (preg_match('/^([a-f0-9]{24})\.(webp|jpg)$/', $file, $m)) {
        @unlink(dirname(__DIR__) . '/media/' . $file);
        @unlink(dirname(__DIR__) . '/media/' . $m[1] . '-800.' . $m[2]);
    }
}

/** "Meet the fleet": vehicles drive in and out of a showroom; real photos replace the drawings once uploaded. */
function fleet_showcase(string $eyebrow = 'Meet the fleet', string $title = 'Cargo vans, Sprinters and box trucks', string $lead = 'The vehicles that keep the LamazonLoads network moving. Tap one to take a closer look.', string $sectionClass = 'section section-white'): void
{
    $keys = array_keys(FLEET);
    ?>
<section class="<?= e($sectionClass) ?>">
  <div class="container">
    <div class="section-head reveal"><span class="eyebrow"><?= e($eyebrow) ?></span><h2><?= e($title) ?></h2><p class="lead"><?= e($lead) ?></p></div>
    <div class="fleet reveal" data-fleet>
      <div class="fleet-stage">
        <div class="fleet-lights" aria-hidden="true"></div>
        <?php foreach ($keys as $i => $k): $photo = site_photo($k); ?>
          <div class="fleet-car<?= $i === 0 ? ' is-on' : '' ?><?= $photo ? ' has-photo' : '' ?>" data-fleet-car>
            <?php if ($photo): ?>
              <img src="<?= e(media_url($photo['file'])) ?>" alt="<?= e($photo['caption'] !== '' ? $photo['caption'] : 'LamazonLoads ' . FLEET[$k]['name']) ?>" loading="lazy">
            <?php else: ?>
              <span class="fleet-speed" aria-hidden="true"><i></i><i></i><i></i></span>
              <?= vehicle_svg($k) ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <div class="fleet-floor" aria-hidden="true"></div>
      </div>
      <div class="fleet-side">
        <div class="fleet-tabs" role="tablist" aria-label="Choose a vehicle">
          <?php foreach ($keys as $i => $k): ?>
            <button type="button" role="tab" class="fleet-tab<?= $i === 0 ? ' on' : '' ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" data-fleet-tab><?= icon(FLEET[$k]['icon']) ?><?= e(FLEET[$k]['name']) ?></button>
          <?php endforeach; ?>
        </div>
        <?php foreach ($keys as $i => $k): $v = FLEET[$k]; ?>
          <div class="fleet-info" data-fleet-info<?= $i === 0 ? '' : ' hidden' ?>>
            <h3><?= e($v['name']) ?></h3>
            <p class="muted"><?= e($v['tag']) ?></p>
            <div class="fleet-specs">
              <?php foreach ($v['specs'] as [$l, $val]): ?><div><small><?= e($l) ?></small><b><?= e($val) ?></b></div><?php endforeach; ?>
            </div>
            <ul class="checklist">
              <?php foreach ($v['uses'] as $use): ?><li><span class="tick"><?= icon('check') ?></span><span><?= e($use) ?></span></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
        <p class="fleet-note">Typical figures. Actual capacity depends on the vehicle.</p>
        <div class="fleet-actions">
          <a class="btn btn-primary" href="<?= e(url('drivers.php')) ?>">Drive with us <?= icon('arrow') ?></a>
          <a class="btn btn-ghost" href="<?= e(url('partners.php')) ?>">Ship with us</a>
        </div>
      </div>
    </div>
  </div>
</section>
<?php
}

/** "On the road" photo strip; shows nothing until photos are uploaded in Admin → Site photos. */
function gallery_section(string $sectionClass = 'section'): void
{
    $photos = gallery_photos();
    if (!$photos) {
        return;
    }
    ?>
<section class="<?= e($sectionClass) ?>">
  <div class="container">
    <div class="section-head reveal"><span class="eyebrow">On the road</span><h2>The LamazonLoads network at work</h2></div>
    <div class="gallery">
      <?php foreach ($photos as $p): ?>
        <figure class="gallery-item reveal"><img src="<?= e(media_url($p['file'])) ?>" alt="<?= e($p['caption'] !== '' ? $p['caption'] : 'LamazonLoads on the road') ?>" loading="lazy"><?php if ($p['caption'] !== ''): ?><figcaption><?= e($p['caption']) ?></figcaption><?php endif; ?></figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php
}
