<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/*
 * Driver application form (every job post) and the onboarding email that goes out after someone applies:
 *  - Box Truck, Cargo Van or Sprinter Van ticked  -> "Professional Dispatch Services" email
 *  - only SUV and/or Other                        -> "Welcome to the Walmart Daily Route Program (city)" email
 * Both come from info@lamazonloads.com and replies go back there, so drivers can reply with their documents.
 * Walmart cities, start month and pay rate are managed in Admin -> Walmart routes.
 */

const APPLY_VEHICLES = ['box_truck' => 'Box Truck', 'cargo_van' => 'Cargo Van', 'sprinter' => 'Sprinter Van', 'suv' => 'SUV', 'other' => 'Other'];
const DISPATCH_VEHICLES = ['box_truck', 'cargo_van', 'sprinter'];
const VEHICLE_OWNERSHIP = ['owner' => 'Owner-operator (I own it)', 'rented' => 'Rented', 'leased' => 'Leased / financed', 'company' => 'Company or fleet vehicle', 'other' => 'Other'];
const ONBOARDING_EMAILS = ['dispatch' => 'Dispatch email', 'walmart' => 'Walmart email'];
const US_STATES = ['AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
    'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
    'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
    'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
    'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
    'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
    'WI' => 'Wisconsin', 'WY' => 'Wyoming'];

function meta_get(string $k, string $default = ''): string
{
    $v = db_val('SELECT v FROM meta WHERE k = ?', [$k]);
    return $v === null || $v === false ? $default : (string) $v;
}

function meta_set(string $k, string $v): void
{
    db_run('INSERT INTO meta (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, mb_substr($v, 0, 255)]);
}

/** Walmart daily route program settings: on/off, start month, pay rate. */
function walmart_settings(): array
{
    static $s = null;
    return $s ??= [
        'on' => meta_get('walmart_on', '1') === '1',
        'start' => meta_get('walmart_start', 'November'),
        'rate' => meta_get('walmart_rate', '$275 per day'),
    ];
}

/** Cities drivers can choose (empty when the program is turned off). */
function walmart_cities(): array
{
    static $cities = null;
    if ($cities === null) {
        $cities = walmart_settings()['on'] ? array_column(db_all('SELECT city FROM walmart_routes WHERE active = 1 ORDER BY city'), 'city') : [];
    }
    return $cities;
}

/** "Routes starting in November · $275 per day" */
function walmart_headline(): string
{
    $s = walmart_settings();
    return trim(($s['start'] !== '' ? 'Routes starting in ' . $s['start'] : '') . ($s['start'] !== '' && $s['rate'] !== '' ? ' · ' : '') . $s['rate']);
}

/** Starting values for the form: from the posted form, or from the member's account. */
function apply_form_values(array $user): array
{
    $parts = preg_split('/\s+/', trim((string) $user['name']), 2);
    $v = ['first_name' => $parts[0] ?? '', 'last_name' => $parts[1] ?? '', 'phone' => (string) $user['phone'], 'location' => (string) ($user['city'] ?? ''),
        'vehicles' => isset(APPLY_VEHICLES[$user['vehicle'] ?? '']) ? [(string) $user['vehicle']] : [], 'vehicle_other' => '', 'ownership' => '', 'ownership_other' => '', 'walmart' => false, 'walmart_city' => '',
        'rate_requested' => '', 'message' => ''];
    if (is_post()) {
        foreach (['first_name' => 60, 'last_name' => 60, 'phone' => 30, 'location' => 120, 'vehicle_other' => 80, 'ownership' => 20,
            'ownership_other' => 80, 'walmart_city' => 80, 'rate_requested' => 40, 'message' => 3000] as $k => $max) {
            $v[$k] = post($k, $max);
        }
        $v['phone'] = format_phone($v['phone']);
        $v['vehicles'] = array_values(array_intersect(array_keys(APPLY_VEHICLES), array_map('strval', (array) ($_POST['vehicles'] ?? []))));
        $v['walmart'] = !empty($_POST['walmart']);
    }
    return $v;
}

/** Checks the form. Returns a list of problems (empty = fine). */
function apply_validate(array &$v): array
{
    $errors = [];
    if ($v['first_name'] === '' || $v['last_name'] === '') $errors[] = 'Please enter your first and last name.';
    if (!preg_match('/^[0-9+()\-. ]{7,25}$/', $v['phone'])) $errors[] = 'Please enter a phone number we can call or text.';
    if (mb_strlen($v['location']) < 2) $errors[] = 'Please tell us where you are (city and state).';
    if (!$v['vehicles']) {
        $errors[] = 'Please choose your vehicle.';
    } elseif (in_array('other', $v['vehicles'], true) && $v['vehicle_other'] === '') {
        $errors[] = 'Please type what your other vehicle is.';
    }
    if (!in_array('other', $v['vehicles'], true)) $v['vehicle_other'] = '';
    if (!isset(VEHICLE_OWNERSHIP[$v['ownership']])) {
        $errors[] = 'Please tell us if you own, rent or lease your vehicle.';
    } elseif ($v['ownership'] === 'other' && $v['ownership_other'] === '') {
        $errors[] = 'Please type how you have your vehicle.';
    }
    if ($v['ownership'] !== 'other') $v['ownership_other'] = '';
    $onlyWalmartVehicles = $v['vehicles'] && !array_intersect($v['vehicles'], DISPATCH_VEHICLES);
    if ($v['walmart']) {
        if (!in_array($v['walmart_city'], walmart_cities(), true)) $errors[] = 'Please choose the city for the Walmart daily route.';
    } else {
        $v['walmart_city'] = '';
        $v['rate_requested'] = '';
        if ($onlyWalmartVehicles) {
            $errors[] = walmart_cities()
                ? 'For SUVs and other vehicles, we currently have openings for the Walmart daily route only. Please tick “Interested in the Walmart daily route” and choose a city.'
                : 'For SUVs and other vehicles, we only have Walmart daily routes, and none are open right now. Please check back soon.';
        }
    }
    return $errors;
}

/** Which onboarding email an application gets: 'dispatch' or 'walmart'. */
function onboarding_email_type(array $app): string
{
    $vehicles = array_filter(explode(',', (string) $app['vehicles']));
    return array_intersect($vehicles, DISPATCH_VEHICLES) ? 'dispatch' : ((int) $app['walmart'] && $app['walmart_city'] !== '' ? 'walmart' : '');
}

/** "Box Truck, Cargo Van, Other: Pickup truck" */
function vehicles_label(array $app): string
{
    $out = [];
    foreach (array_filter(explode(',', (string) $app['vehicles'])) as $k) {
        $out[] = $k === 'other' && $app['vehicle_other'] !== '' ? 'Other: ' . $app['vehicle_other'] : (APPLY_VEHICLES[$k] ?? $k);
    }
    return implode(', ', $out);
}

function ownership_label(array $app): string
{
    return $app['ownership'] === 'other' && $app['ownership_other'] !== '' ? 'Other: ' . $app['ownership_other'] : (VEHICLE_OWNERSHIP[$app['ownership']] ?? '');
}

/** [subject, paragraphs] of the onboarding email, as LamazonLoads wrote them (documents are now uploaded on the website: the "Upload my documents" button follows the list). */
function onboarding_email_content(string $type, string $firstName, string $city): array
{
    if ($type === 'walmart') {
        return ['Welcome to the Walmart Daily Route Program – ' . $city, [
            'Hello ' . $firstName . ',',
            'Welcome to LamazonLoads! We’re excited to have you join our team for the Walmart daily route in the ' . $city . ' area. We look forward to a successful working relationship.',
            "To complete your onboarding process, please sign in to your LamazonLoads account and upload the following documents and information as soon as possible:\n\n• Completed W-9 Form\n• Proof of Insurance\n• Clear Photo of Your Driver’s License\n• Photo of Your Vehicle\n• Payment Information (Zelle preferred)",
            'Once we receive and review these items, we will finalize your onboarding and provide your route details, scheduling information, and next steps.',
            'If you have any questions, please feel free to reach out. Thank you for your cooperation—we are excited to have you on board and wish you success on the road!',
            "Best regards,\nLamazonLoads Team",
        ]];
    }
    return ['Professional Dispatch Services for Cargo Vans & Box Trucks – LamazonLoads', [
        'Hello ' . $firstName . ', from LamazonLoads,',
        'We are a professional dispatch team specializing in cargo vans, sprinters, and box trucks. We’re currently offering our dispatch services and would love to work with you to keep you loaded with the best available options.',
        "Our process is simple and transparent:\n\n• We handle load searching, rate negotiation, and booking\n• We keep you updated and support you throughout the process\n• Payments are processed within 2-3 business days\n• Our dispatch fee is 10% per booked load",
        "To get you set up and start working together, please sign in to your LamazonLoads account and upload the following documents:\n\n• Pictures of Vehicle\n• W-9 Form\n• Proof of Insurance\n• Driver’s License (clear picture)\n• Payment details (Zelle preferred)",
        'Once our team reviews and approves your documents, you’ll sign your agreement online, and then we can get you active and start booking loads right away.',
        'Looking forward to working with you.',
        "Best regards,\nLamazonLoads Team",
    ]];
}

/**
 * Sends the right onboarding email for an application (from info@lamazonloads.com, replies go to info@)
 * and records which one went out. Skips it if the same email already went to this person in the last 30 days
 * (for example when they apply to several job posts).
 */
function send_onboarding_email(int $appId, bool $again = false): string
{
    $a = db_one('SELECT a.*, u.email, u.name FROM applications a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$appId]);
    if (!$a) {
        return '';
    }
    $type = onboarding_email_type($a);
    if ($type === '') {
        return '';
    }
    $city = $type === 'walmart' ? (string) $a['walmart_city'] : '';
    onboarding_start((int) $a['user_id'], $type); // their onboarding page: upload documents → review → agreement → Telegram
    $recent = db_val('SELECT email_sent_at FROM applications WHERE user_id = ? AND id <> ? AND email_sent = ?'
        . ($type === 'walmart' ? ' AND walmart_city = ?' : '') . ' AND email_sent_at > NOW() - INTERVAL 30 DAY ORDER BY email_sent_at DESC LIMIT 1',
        $type === 'walmart' ? [$a['user_id'], $appId, $type, $city] : [$a['user_id'], $appId, $type]);
    if ($recent && !$again) { // $again: the driver tapped "Resend email"
        db_run('UPDATE applications SET email_sent = ?, email_sent_at = ? WHERE id = ?', [$type, $recent, $appId]);
        app_auto_note($appId, ONBOARDING_EMAILS[$type] . ' already sent ' . fmt_date((string) $recent, 'M j') . ', not sent again');
        return $type;
    }
    $first = $a['first_name'] !== '' ? (string) $a['first_name'] : chat_first_name((string) $a['name']);
    [$subject, $paras] = onboarding_email_content($type, $first, $city);
    // The button sits right under the list of documents
    [$text, $html] = email_body('', $paras, 'Upload my documents', onboarding_link((int) $a['user_id']), '', true, $type === 'walmart' ? 2 : 3);
    if (send_mail((string) $a['email'], $subject, $text, $html, support_email())) {
        db_run('UPDATE applications SET email_sent = ?, email_sent_at = NOW() WHERE id = ?', [$type, $appId]);
        return $type;
    }
    app_auto_note($appId, ONBOARDING_EMAILS[$type] . ' could not be sent (check Admin → Email check)');
    return '';
}

/**
 * Every US city and town ("Atlanta, GA"), one per line in assets/data/us-cities.txt: the USPS city names of all
 * ZIP codes in the 50 states and DC (from the BSD-licensed "zipcodes" package). The sign-up city picker
 * searches this list, and a city is only accepted if it is on it.
 */
function us_city_valid(string $city): bool
{
    static $all = null;
    $all ??= "\n" . (string) file_get_contents(dirname(__DIR__) . '/assets/data/us-cities.txt');
    return $city !== '' && str_contains($all, "\n" . $city . "\n");
}

/** Drivers say which vehicle they have when they sign up. */
function account_drives(string $type): bool
{
    return in_array($type, ['owner_operator', 'driver'], true);
}

/** The searchable city picker (see [data-city-pick] in app.js). $name is the form field that gets "City, ST". */
function city_picker(string $name, string $value, string $id = 'city', string $label = 'City and state', bool $required = false): string
{
    return '<div class="city-pick" data-city-pick' . ($required ? ' data-required' : '') . ' data-src="' . e(asset('data/us-cities.txt')) . '">'
        . '<label for="' . e($id) . '">' . e($label) . '</label>'
        . '<div class="city-field"><span class="city-ico" aria-hidden="true">' . icon('pin') . '</span>'
        . '<input id="' . e($id) . '" type="text" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="' . e($id) . '-list" autocomplete="off" spellcheck="false" placeholder="Start typing your city…" value="' . e($value) . '" data-city-input>'
        . '<span class="city-ok" aria-hidden="true">' . icon('check') . '</span></div>'
        . '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '" data-city-value>'
        . '<ul class="city-list" id="' . e($id) . '-list" role="listbox" hidden></ul>'
        . '<p class="hint" data-city-hint>Choose your city from the list.</p></div>';
}

