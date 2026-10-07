<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

crud_page([
    'table' => 'faqs',
    'active' => 'faqs',
    'title' => 'FAQs',
    'singular' => 'question',
    'anchor' => '#faq',
    'intro' => 'Questions and answers in the FAQ section. Make sure the answers match how you work — your timelines, payment terms and what\'s included.',
    'title_field' => 'question',
    'row' => fn(array $r) => '<small class="clip">' . e(mb_strimwidth($r['answer'], 0, 140, '…')) . '</small>',
    'fields' => [
        'question' => ['label' => 'Question', 'required' => true, 'max' => 200, 'wide' => true],
        'answer' => ['label' => 'Answer', 'type' => 'textarea', 'required' => true, 'max' => 2000],
        'is_active' => ['label' => 'Show on the website', 'type' => 'checkbox', 'default' => 1],
    ],
]);
