<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

crud_page([
    'table' => 'testimonials',
    'active' => 'testimonials',
    'title' => 'Testimonials',
    'singular' => 'testimonial',
    'anchor' => '#reviews',
    'intro' => 'Reviews from your clients. The "What our clients say" section appears on the website as soon as you add one — only add real reviews, with your client\'s permission.',
    'empty' => 'When happy clients leave you a review, add it here. The reviews section stays hidden until then.',
    'title_field' => 'name',
    'thumb' => fn(array $r) => '<span class="avatar">' . e(initials($r['name'])) . '</span>',
    'row' => fn(array $r) => '<small>' . str_repeat('★', (int) $r['rating']) . ($r['role'] !== '' ? ' · ' . e($r['role']) : '') . '</small><small class="clip">“' . e(mb_strimwidth($r['quote'], 0, 110, '…')) . '”</small>',
    'fields' => [
        'name' => ['label' => 'Client name', 'required' => true, 'max' => 80, 'placeholder' => 'e.g. Maria Santos'],
        'role' => ['label' => 'Business / role', 'max' => 100, 'placeholder' => 'e.g. Owner, Sweet Crumbs Bakery'],
        'quote' => ['label' => 'Review', 'type' => 'textarea', 'required' => true, 'max' => 800],
        'rating' => ['label' => 'Stars', 'type' => 'rating'],
        'is_active' => ['label' => 'Show on the website', 'type' => 'checkbox', 'default' => 1],
    ],
]);
