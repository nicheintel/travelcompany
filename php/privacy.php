<?php
require __DIR__ . '/includes/bootstrap.php';

$site = e((string) config('site_name'));
$who = business_identity();
$mail = contact_email_link();
$description = 'How FareFinders collects, uses and protects your personal information.';
$title = 'Privacy policy';
require __DIR__ . '/includes/header.php';
echo legal_page('Privacy policy', "This policy explains what personal information $site collects when you use our website and book travel with us, why we need it, who we share it with and the choices you have.", 'October 6, 2026', [
    'who' => ['Who we are', "<p>This website is run by $who, a travel assistant that helps customers find and book flights, hotels and travel packages. We decide how your personal information is used for the purposes below. Questions or requests: $mail.</p>"],
    'collect' => ['Information we collect', '<ul>
        <li><strong>Account details:</strong> your name, email address and password. Passwords are stored only in a securely scrambled (hashed) form — we can\'t see them.</li>
        <li><strong>Booking details:</strong> each traveler\'s name, date of birth, gender and nationality as shown on their passport or ID; any frequent flyer number and baggage requests you give us; a contact name, email and phone number; and the trip you chose and its price. Airlines require these details to issue tickets.</li>
        <li><strong>Payment details:</strong> payments are made on PayPal\'s (or Stripe\'s) own secure pages. We receive confirmation that you paid and a payment reference — <strong>never your card number</strong>.</li>
        <li><strong>Messages:</strong> anything you send us by email, WhatsApp or phone, and notes our travel assistants add to your booking to help you.</li>
        <li><strong>Technical information:</strong> your IP address, used briefly to protect the site against password guessing and abuse, and one cookie that keeps you signed in (see our <a href="' . e(url('cookies.php')) . '">Cookie policy</a>). We don\'t use advertising or tracking tools.</li>
    </ul>'],
    'use' => ['How we use it', '<ul>
        <li>To create and run your account, and to confirm your email address.</li>
        <li>To check prices and availability, reserve, ticket and manage your trips with airlines and hotels.</li>
        <li>To take payments and send confirmations, payment links, tickets and changes about your trips.</li>
        <li>To answer your questions and help with changes, cancellations and refunds.</li>
        <li>To keep the website and your account secure, and to meet legal, tax and accounting obligations.</li>
    </ul><p>We do <strong>not</strong> sell your personal information, and we don\'t send marketing emails unless you ask us to.</p>'],
    'basis' => ['Why we\'re allowed to use it', '<p>We use your information because we need it to provide the bookings and account you asked for (performing our agreement with you), to meet legal obligations, and for our legitimate interest in keeping the service secure. Where the law requires your consent, we ask for it, and you can withdraw it at any time.</p>'],
    'share' => ['Who we share it with', '<p>Only with the companies needed to provide your trip and run this website:</p>
        <table><tr><th>Who</th><th>Why</th></tr>
        <tr><td>Airlines, via our flight suppliers (Duffel and LiteAPI)</td><td>To search fares and issue your tickets (traveler names, dates of birth, gender, nationality and contact details).</td></tr>
        <tr><td>Hotels, via our hotel supplier LiteAPI</td><td>To search rates and book your room (guest names and contact details).</td></tr>
        <tr><td>PayPal (or Stripe)</td><td>To process your payment on their secure pages.</td></tr>
        <tr><td>Hostinger</td><td>Hosts this website, its database and our email.</td></tr>
        <tr><td>Google Fonts and Unsplash</td><td>Deliver the website\'s font and some photos; your browser connects to them directly, which shares your IP address with them.</td></tr>
        </table>
        <p>We may also share information when the law requires it, or to protect our customers and business from fraud. Airlines and hotels use your information under their own privacy policies.</p>'],
    'abroad' => ['International transfers', '<p>Our suppliers and your airlines and hotels may be located in other countries, including outside the Philippines and the European Union. We only share what\'s needed for your trip, and we use established providers that protect personal information.</p>'],
    'keep' => ['How long we keep it', '<ul>
        <li><strong>Your account:</strong> until you ask us to delete it.</li>
        <li><strong>Bookings and payment records:</strong> as long as the law requires us to keep business and tax records (usually several years), even if your account is deleted.</li>
        <li><strong>Security records</strong> such as sign-in attempt counters: a few hours at most.</li>
    </ul>'],
    'rights' => ['Your rights', "<p>Depending on where you live (including under the Philippine Data Privacy Act of 2012 and, for people in Europe, the GDPR), you can ask us to:</p>
        <ul><li>tell you what personal information we hold about you and give you a copy;</li><li>correct information that is wrong (you can change your name and email yourself in Account settings);</li>
        <li>delete your account and personal information, unless we must keep it by law;</li><li>stop or limit some uses of your information, or object to them.</li></ul>
        <p>Email $mail from the address on your account. We'll reply within 30 days. You can also complain to your data protection authority — in the Philippines, the National Privacy Commission.</p>"],
    'security' => ['How we protect it', '<p>The website uses an encrypted (https) connection, hashed passwords, protection against password guessing and forged requests, and limits who on our team can see bookings. No system is perfectly secure, but we work to protect your information and will tell you if a breach affects you, as the law requires.</p>'],
    'children' => ['Children', '<p>You must be 18 or older to create an account. Parents or guardians can book trips that include children; we only use children\'s details to book their travel.</p>'],
    'changes' => ['Changes to this policy', '<p>If we change this policy, we\'ll update the date at the top of this page, and tell you by email about important changes.</p>'],
]);
require __DIR__ . '/includes/footer.php';
