<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

crud_page([
    'table' => 'projects',
    'active' => 'portfolio',
    'title' => 'Portfolio',
    'singular' => 'project',
    'anchor' => '#work',
    'intro' => 'Projects in the "Our work" section. Upload a screenshot of each website, or leave it empty to show a colorful preview. The starter projects are marked "Design concept" — replace them with your real client work as it comes in.',
    'empty' => 'Add a project and it will appear in the "Our work" section. With no projects, the section is hidden.',
    'title_field' => 'title',
    'thumb' => function (array $r): string {
        $img = uploaded_image_url($r['image']);
        $t = THEMES[$r['theme']] ?? THEMES['royal'];
        return $img ? '<img src="' . e($img) . '" alt="">' : '<span class="thumb-theme" style="--ta:' . e($t['a']) . ';--tb:' . e($t['b']) . '"></span>';
    },
    'row' => fn(array $r) => '<small>' . e($r['category']) . ($r['url'] !== '' ? ' · ' . e(parse_url($r['url'], PHP_URL_HOST) ?: $r['url']) : '') . '</small>'
        . ($r['is_concept'] ? '<span class="badge badge-blue">Design concept</span>' : ''),
    'fields' => [
        'title' => ['label' => 'Project / business name', 'required' => true, 'max' => 80, 'placeholder' => 'e.g. Brew & Bloom Café'],
        'category' => ['label' => 'Category', 'required' => true, 'max' => 40, 'placeholder' => 'e.g. Restaurants', 'help' => 'Projects with the same category are grouped in the filter buttons.'],
        'summary' => ['label' => 'Short description', 'max' => 240, 'wide' => true, 'placeholder' => 'e.g. Warm, menu-first website with online table reservations.'],
        'url' => ['label' => 'Live website address', 'type' => 'url', 'max' => 250, 'wide' => true, 'placeholder' => 'https://', 'help' => 'Optional. Adds a "Visit website" button.'],
        'image' => ['label' => 'Screenshot', 'type' => 'image', 'help' => 'JPG, PNG or WebP. A wide screenshot of the top of the website works best.'],
        'theme' => ['label' => 'Preview colors (used when there\'s no screenshot)', 'type' => 'theme'],
        'is_concept' => ['label' => 'Design concept', 'type' => 'checkbox', 'help' => 'Shows a "Design concept" tag — for sample designs that aren\'t real client projects.'],
        'is_active' => ['label' => 'Show on the website', 'type' => 'checkbox', 'default' => 1],
    ],
]);
