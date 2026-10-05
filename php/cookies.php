<?php
require __DIR__ . '/includes/bootstrap.php';

$site = e((string) config('site_name'));
$title = 'Cookie policy';
require __DIR__ . '/includes/header.php';
echo legal_page('Cookie policy', "$site uses as few cookies as possible: just one, to keep you signed in. We don't use advertising, analytics or tracking cookies, so there's nothing to accept or reject.", 'October 5, 2026', [
    'what' => ['What cookies are', '<p>Cookies are small text files a website stores in your browser so it can remember you between pages — for example, that you\'re signed in.</p>'],
    'ours' => ['The cookie we use', '<table><tr><th>Name</th><th>Purpose</th><th>How long</th></tr>
        <tr><td><code>tc_session</code> (or <code>__Secure-tc_session</code>)</td><td>Keeps you signed in and protects forms against forged requests. Strictly necessary — the website can\'t work without it.</td><td>Up to 30 days, or until you sign out</td></tr></table>
        <p>Because this cookie is strictly necessary, the law doesn\'t require us to ask for consent, and we don\'t show a cookie banner.</p>'],
    'others' => ['Other websites', '<p>Pages load the website\'s font from Google Fonts and some photos from Unsplash. Your browser connects to them to download these files, which shares your IP address with them, but we don\'t ask them to set cookies. When you pay, you\'re taken to PayPal (or Stripe), whose own cookie policies apply on their pages.</p>'],
    'control' => ['Controlling cookies', '<p>You can delete or block cookies in your browser settings. If you block the sign-in cookie, you won\'t be able to sign in or book.</p>'],
    'contact' => ['Questions', '<p>See our <a href="' . e(url('privacy.php')) . '">Privacy policy</a> or contact us at ' . contact_email_link() . '.</p>'],
]);
require __DIR__ . '/includes/footer.php';
