<?php
/* Saves the visitor's language or currency choice (one cookie each), then goes back to the page they were on. */
require __DIR__ . '/includes/bootstrap.php';

$cookie = fn(string $name, string $value) => setcookie($name, $value, [
    'expires' => time() + 365 * 86400,
    'path' => base_path() . '/',
    'secure' => request_is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
$lang = param('lang');
if (isset(LANGUAGES[$lang])) $cookie('tc_lang', $lang);
$cur = strtoupper(param('cur'));
if (isset(CURRENCIES[$cur])) $cookie('tc_cur', $cur);
redirect(safe_next(param('next'), url()));
