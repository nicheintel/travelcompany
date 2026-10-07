<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

crud_page([
    'table' => 'packages',
    'active' => 'packages',
    'title' => 'Pricing packages',
    'singular' => 'package',
    'anchor' => '#pricing',
    'intro' => 'The packages in the Pricing section of your website. Use the arrows to change the order, and mark one as "Most popular" to make it stand out.',
    'title_field' => 'name',
    'row' => fn(array $r) => '<small>' . e($r['price'] === null ? 'Custom quote' : money($r['price']) . ($r['price_note'] !== '' ? ' · ' . $r['price_note'] : ''))
        . ' · ' . plural(count(lines($r['features'])), 'feature') . '</small>'
        . ($r['is_featured'] ? '<span class="badge badge-orange">' . icon('star') . ' Most popular</span>' : ''),
    'fields' => [
        'name' => ['label' => 'Package name', 'required' => true, 'max' => 80, 'placeholder' => 'e.g. Business'],
        'price' => ['label' => 'Price (' . setting('currency') . ')', 'type' => 'price', 'placeholder' => 'e.g. 12999', 'help' => 'Leave empty to show "Custom quote".'],
        'tagline' => ['label' => 'Short description', 'max' => 160, 'wide' => true, 'placeholder' => 'e.g. The complete website for a growing business.'],
        'price_note' => ['label' => 'Price note', 'max' => 40, 'placeholder' => 'e.g. one-time', 'help' => 'Small text after the price.'],
        'features' => ['label' => 'What\'s included', 'type' => 'lines', 'required' => true, 'max' => 3000, 'help' => 'One item per line.', 'placeholder' => "Up to 5 pages\nMobile-friendly design\nContact form"],
        'is_featured' => ['label' => 'Most popular', 'type' => 'checkbox', 'help' => 'Highlight this package in royal blue with a "Most popular" ribbon.'],
        'is_active' => ['label' => 'Show on the website', 'type' => 'checkbox', 'default' => 1],
    ],
]);
