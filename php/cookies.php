<?php
require __DIR__ . '/includes/bootstrap.php';

$site = e((string) config('site_name'));
$description = 'FareFinders uses a sign-in cookie, two preference cookies and no tracking cookies.';
$title = 'Cookie policy';
require __DIR__ . '/includes/header.php';
echo legal_page('Cookie policy', "$site uses as few cookies as possible: one to keep you signed in, two that remember the language and currency you pick, and one that remembers your chat if you message us. We don't use advertising, analytics or tracking cookies, so there's nothing to accept or reject.", 'October 6, 2026', [
    'what' => ['What cookies are', '<p>Cookies are small text files a website stores in your browser so it can remember you between pages — for example, that you\'re signed in.</p>'],
    'ours' => ['The cookies we use', '<table><tr><th>Name</th><th>Purpose</th><th>How long</th></tr>
        <tr><td><code>tc_session</code> (or <code>__Secure-tc_session</code>)</td><td>Keeps you signed in and protects forms against forged requests. Strictly necessary — the website can\'t work without it.</td><td>Up to 30 days, or until you sign out</td></tr>
        <tr><td><code>tc_lang</code></td><td>Remembers the language you chose. Only set when you pick a language.</td><td>1 year</td></tr>
        <tr><td><code>tc_chat</code></td><td>Remembers your conversation if you use the Chat button without signing in (a random number; we only store a scrambled copy). Only set when you send a chat message.</td><td>1 year</td></tr>
        <tr><td><code>tc_cur</code></td><td>Remembers the currency you chose for showing prices. Only set when you pick a currency.</td><td>1 year</td></tr></table>
        <p>The sign-in cookie is strictly necessary, and the language, currency and chat cookies only store a choice you made or a conversation you started, so the law doesn\'t require us to ask for consent, and we don\'t show a cookie banner. None of them track you.</p>'],
    'others' => ['Other websites', '<p>Some photos load from Unsplash. Your browser connects to it to download them, which shares your IP address with it, but we don\'t ask it to set cookies. When you pay, you\'re taken to PayPal (or Stripe), whose own cookie policies apply on their pages.</p>'],
    'control' => ['Controlling cookies', '<p>You can delete or block cookies in your browser settings. If you block the sign-in cookie, you won\'t be able to sign in or book.</p>'],
    'contact' => ['Questions', '<p>See our <a href="' . e(url('privacy.php')) . '">Privacy policy</a> or contact us at ' . contact_email_link() . '.</p>'],
]);
require __DIR__ . '/includes/footer.php';
