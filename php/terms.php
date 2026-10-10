<?php
require __DIR__ . '/includes/bootstrap.php';

$site = e((string) config('site_name'));
$who = business_identity();
$mail = contact_email_link();
$fee = change_service_fee();
$feeText = money($fee);
$carePct = round(travel_care_rate() * 100);
$description = 'The terms for using FareFinders and booking flights, hotels and packages with us.';
$title = 'Terms of use';
require __DIR__ . '/includes/header.php';
echo legal_page('Terms of use', "These terms apply when you use the $site website and when you book travel through us. Please read them before booking — by creating an account or making a reservation you agree to them.", 'October 9, 2026', [
    'about' => ['About us', "<p>$who is a travel assistant. We help you find flights, hotels and travel packages and book them with airlines, hotels and other travel companies (the <strong>suppliers</strong>). We act as your booking agent: the flight or stay itself is provided by the supplier, under its own conditions of carriage or hotel rules.</p>"],
    'account' => ['Your account', '<ul>
        <li>You must be 18 or older and give accurate information.</li>
        <li>You need to confirm your email address before booking, because confirmations and tickets are sent there.</li>
        <li>Keep your password private. You\'re responsible for bookings made from your account; tell us straight away if you think someone else has used it.</li>
        <li>We may close accounts used for fraud or misuse of the website.</li>
    </ul>'],
    'prices' => ['Prices', '<ul>
        <li>Prices are shown in US dollars and include taxes and our service fee, unless we say otherwise.</li>
        <li>Flight and hotel prices come live from our suppliers and can change until your booking is paid and ticketed.</li>
        <li>The member discount shown at checkout applies to flights and hotels. Promo packages are sold at their fixed promotional price.</li>
        <li>Your bank or card provider may charge its own fees, for example for currency conversion.</li>
    </ul>'],
    'bookings' => ['Reservations, payment and tickets', '<ol class="ml-5 list-decimal space-y-1.5 text-slate-700">
        <li><strong>Reservation.</strong> When you click <em>Reserve</em>, we record your trip at the price shown. No money is taken yet, and the airline seat or hotel room is <strong>not guaranteed</strong> until it\'s paid and ticketed.</li>
        <li><strong>Payment.</strong> Pay online through PayPal (account or card), or as arranged with a travel assistant. Unpaid reservations may be cancelled after 24 hours, or sooner for trips departing soon.</li>
        <li><strong>Ticketing.</strong> After payment, a travel assistant buys your flight or room from the supplier and emails you the confirmation code (for example, the airline booking reference). Your trip is confirmed when you receive it.</li>
        <li><strong>Price changes before ticketing.</strong> If the supplier\'s price rises or the fare is no longer available before we can ticket, we\'ll contact you. You can accept the new price, choose another option, or cancel for a full refund of what you paid us.</li>
    </ol>'],
    'travellers' => ['Traveler details and documents', '<ul>
        <li>Names must match each traveler\'s passport or ID exactly. Correcting a name later may not be possible or may cost a fee set by the airline.</li>
        <li>You are responsible for valid passports, visas, health and entry requirements for every country on your trip, and for arriving on time for check-in.</li>
    </ul>'],
    'changes' => ['Changes, cancellations and refunds', '<ul>
        <li><strong>Before payment:</strong> you can cancel your reservation for free on your trip page.</li>
        <li><strong>After payment:</strong> changes and cancellations follow the airline\'s fare rules and the hotel\'s policy. Many low fares are non-refundable. Contact us and we\'ll tell you what\'s possible and any supplier fees before anything is changed.</li>
        ' . ($fee > 0
            ? "<li>When we change or cancel a paid booking for you, our service fee is <strong>$feeText</strong> per change or cancellation, on top of the supplier's own fees. We tell you the full cost before anything is changed. There's no service fee if you have Travel Care Protection.</li>"
            : "<li>We don't add our own fee on top of the supplier's rules.</li>") . '
        <li>Refunds are paid to your original payment method once the supplier has refunded us. This can take several weeks, depending on the supplier.</li>
        <li><strong>If the supplier cancels or changes your trip</strong> (for example a schedule change), we\'ll tell you and help you with the alternatives or refund the supplier offers.</li>
    </ul>'],
    'travel-care' => ['Travel Care Protection', ($carePct > 0 ? "<p>Travel Care Protection is an optional add-on for flight bookings. You can add it on your trip page before you pay, and it costs <strong>$carePct% of your ticket price</strong> (the amount is shown before you add it).</p>" : "<p>Travel Care Protection is an optional add-on for flight bookings that we may offer on your trip page before you pay. Its price is shown before you add it.</p>") . '<ul>
        <li><strong>No service fee from us</strong> when you change the dates of your trip or cancel it' . ($fee > 0 ? " (normally $feeText each time)" : '') . '.</li>
        <li><strong>Priority help:</strong> your change and cancellation requests are handled first by our travel assistants.</li>
        <li>The airline\'s own penalties, fare differences and refund rules still apply. Travel Care doesn\'t make a non-refundable ticket refundable.</li>
        <li>Travel Care is a service from ' . $site . ', not an insurance policy. It doesn\'t cover medical costs, lost baggage or other losses, so consider separate travel insurance for those.</li>
        <li>It can only be added before you pay, and it is non-refundable once paid, except when your booking can\'t be ticketed and we refund everything you paid.</li>
    </ul>'],
    'tips' => ['Tips', '<p>Before you pay, you can add an optional tip for your travel assistant ("How was my service?"). No tip is selected unless you choose one, the amount is shown in your total before you pay, and it is charged together with your trip. If your booking can\'t be ticketed and we refund what you paid, we refund the tip too.</p>'],
    'packages' => ['Promo packages', '<p>Packages include exactly what is listed on the package (for example, round-trip flights and a hotel stay). Prices are per person and are only available for the departure dates shown. Each part of the package is provided by its supplier under its own rules.</p>'],
    'liability' => ['Our responsibility', '<p>We\'ll take reasonable care in arranging your bookings. We aren\'t responsible for the supplier\'s own services, delays, cancellations, overbooking or events outside our control (such as weather, strikes or government restrictions), but we\'ll help you deal with the supplier. Nothing in these terms limits rights you have under consumer protection law that can\'t be excluded.</p>'],
    'site' => ['Using this website', '<p>Please don\'t misuse the website — for example, by trying to break its security, making fake bookings or copying its content in bulk. Prices and availability on the website are information to help you book, not an offer until your booking is confirmed.</p>'],
    'law' => ['Law and complaints', "<p>These terms are governed by the laws of the Republic of the Philippines. If something goes wrong, please contact us first at $mail and we'll do our best to fix it.</p>"],
    'changes-terms' => ['Changes to these terms', '<p>We may update these terms. The version shown when you booked applies to that booking.</p>'],
]);
require __DIR__ . '/includes/footer.php';
