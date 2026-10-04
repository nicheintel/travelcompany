<?php
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) redirect(url());
verify_csrf();
logout_user();
redirect(url());
