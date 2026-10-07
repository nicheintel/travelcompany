<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

if (is_post()) {
    verify_csrf();
    logout_admin();
    flash('You\'ve signed out.');
}
redirect(url('admin/login.php'));
