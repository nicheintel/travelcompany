<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    csrf_check();
    logout_user();
    flash('success', 'You are signed out. See you on the road!');
}
redirect('');
