<?php
declare(strict_types=1);
defined('WS_APP') || exit;

/**
 * Where a website request is. Admin label, what the client sees on their tracking page, and
 * the color of its badge.
 */
const STATUSES = [
    'new' => ['label' => 'New', 'client' => 'Request received', 'tone' => 'orange'],
    'contacted' => ['label' => 'Contacted', 'client' => 'Free consultation', 'tone' => 'blue'],
    'quoted' => ['label' => 'Quote sent', 'client' => 'Quote & plan', 'tone' => 'violet'],
    'in_progress' => ['label' => 'In progress', 'client' => 'Design & build', 'tone' => 'sky'],
    'launched' => ['label' => 'Launched', 'client' => 'Website launched', 'tone' => 'green'],
    'closed' => ['label' => 'Closed', 'client' => 'Closed', 'tone' => 'gray'],
];

/** The steps a client sees on their tracking page (closed is shown separately). */
const CLIENT_STEPS = ['new', 'contacted', 'quoted', 'in_progress', 'launched'];

const BUSINESS_TYPES = [
    'Restaurant / Café', 'Retail / Online shop', 'Beauty / Salon / Spa', 'Health / Clinic',
    'Real estate', 'Construction / Services', 'Education / Training', 'Fitness / Sports',
    'Travel / Tourism', 'Professional services', 'Non-profit / Church', 'Personal brand / Portfolio', 'Other',
];

const BUDGETS = ['Under 5,000', '5,000 – 10,000', '10,000 – 20,000', '20,000 – 40,000', '40,000+', 'Not sure yet'];

const TIMELINES = ['As soon as possible', 'Within 2 weeks', 'Within a month', '1–3 months', 'Just exploring'];

/** Color themes for portfolio previews (when there's no screenshot). */
const THEMES = [
    'royal' => ['name' => 'Royal blue', 'a' => '#1a3cb0', 'b' => '#3b6af0', 'accent' => '#f9812a'],
    'sunset' => ['name' => 'Sunset', 'a' => '#b4401a', 'b' => '#f59e0b', 'accent' => '#fff1d6'],
    'rose' => ['name' => 'Rose', 'a' => '#9d174d', 'b' => '#f472b6', 'accent' => '#fde4ef'],
    'teal' => ['name' => 'Teal', 'a' => '#0f5e63', 'b' => '#14b8a6', 'accent' => '#d5f7f1'],
    'midnight' => ['name' => 'Midnight', 'a' => '#0b1020', 'b' => '#334155', 'accent' => '#f9812a'],
    'forest' => ['name' => 'Forest', 'a' => '#14532d', 'b' => '#4d9a5b', 'accent' => '#f3e8c8'],
    'volt' => ['name' => 'Volt', 'a' => '#111827', 'b' => '#2350d8', 'accent' => '#c6f432'],
    'grape' => ['name' => 'Grape', 'a' => '#4c1d95', 'b' => '#8b5cf6', 'accent' => '#ede4ff'],
    'sky' => ['name' => 'Sky', 'a' => '#075985', 'b' => '#38bdf8', 'accent' => '#e0f4ff'],
];

function status_label(string $status): string
{
    return STATUSES[$status]['label'] ?? ucfirst($status);
}

function status_badge(string $status): string
{
    $tone = STATUSES[$status]['tone'] ?? 'gray';
    return '<span class="badge badge-' . e($tone) . '">' . e(status_label($status)) . '</span>';
}

// ---------- Public content ----------

function active_packages(): array
{
    return db_all('SELECT * FROM packages WHERE is_active = 1 ORDER BY sort_order, id');
}

function active_projects(): array
{
    return db_all('SELECT * FROM projects WHERE is_active = 1 ORDER BY sort_order, id');
}

function active_testimonials(): array
{
    return db_all('SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort_order, id');
}

function active_faqs(): array
{
    return db_all('SELECT * FROM faqs WHERE is_active = 1 ORDER BY sort_order, id');
}

function uploaded_image_url(?string $file): string
{
    return $file && preg_match('/^[a-f0-9]{32}\.jpg$/', $file) ? url('uploads/' . $file) : '';
}

// ---------- Requests ----------

/** Short reference customers can quote, e.g. "WS-7K3QP9" (no 0/O or 1/I to mix up). */
function new_request_ref(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $ref = 'WS-';
        for ($i = 0; $i < 6; $i++) {
            $ref .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
    } while (db_value('SELECT 1 FROM requests WHERE ref = ?', [$ref]));
    return $ref;
}

function track_url(array $request): string
{
    return app_url() . '/track.php?t=' . $request['track_token'];
}

function add_event(int $requestId, ?int $adminId, string $kind, string $body): void
{
    db_run(
        'INSERT INTO request_events (request_id, admin_id, kind, body, created_at) VALUES (?, ?, ?, ?, ?)',
        [$requestId, $adminId, $kind, $body, now_utc()],
    );
}

function new_request_count(): int
{
    return (int) db_value("SELECT COUNT(*) FROM requests WHERE status = 'new'");
}
