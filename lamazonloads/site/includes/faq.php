<?php
declare(strict_types=1);
defined('LL_APP') || exit;

/*
 * Driver FAQ (faq.php). Answers: one paragraph per line; lines starting with "- " become a bullet list.
 * To change an answer, edit the text below. Keys (e.g. 'what') are used for links like faq.php#what.
 */
const FAQ = [
    'about' => ['About LamazonLoads', 'truck', [
        'what' => ['What is LamazonLoads?',
            'LamazonLoads is a driver-focused dispatch and route support company that helps owner-operators and independent drivers find available loads, daily routes and delivery opportunities.'],
        'opportunities' => ['What types of opportunities does LamazonLoads offer?',
            "Depending on availability, we offer:\n- Daily delivery routes\n- Dedicated routes\n- Local loads\n- OTR loads\n- Walmart delivery routes\n- Contract delivery opportunities\n- General freight opportunities\nAvailability changes by city and market."],
        'guarantee' => ['Does LamazonLoads guarantee loads?',
            "No. Load and route availability depends on the driver's location, vehicle type, market conditions, customer demand and available contracts. We work to provide drivers with the best opportunities available."],
        'nationwide' => ['Does LamazonLoads operate nationwide?',
            'We work with drivers in multiple states throughout the United States. Opportunities depend on the locations where routes and loads are currently available.'],
    ]],
    'vehicles' => ['Vehicles', 'van', [
        'vehicle' => ['What type of vehicle do I need?',
            "Opportunities may be available for:\n- Cargo Vans\n- Sprinter Vans\n- Box Trucks\nCertain programs may occasionally accept other vehicle types, depending on the specific route requirements."],
        'own' => ['Do I need to own my vehicle?',
            'Most owner-operator opportunities require you to have access to your own qualifying vehicle.'],
        'suv' => ['Can I use an SUV?',
            'Only when a specific route allows SUVs. Most of our regular opportunities currently require a Cargo Van, Sprinter Van or Box Truck.'],
        'dimensions' => ['Why do you need my vehicle dimensions?',
            "Vehicle dimensions help dispatch determine whether freight will safely fit inside your vehicle before submitting or booking a load.\nWe may request:\n- Cargo length\n- Cargo width\n- Cargo height\n- Door opening dimensions"],
        'rental' => ['Can I use a rental vehicle?',
            'This depends on the specific contract or route. The vehicle must meet the program requirements and have appropriate insurance.'],
    ]],
    'onboarding' => ['Onboarding', 'clipboard', [
        'start' => ['How do I get started with LamazonLoads?',
            "Send us your:\n- Full name\n- Phone number\n- Email address\n- Current ZIP code\n- City and state\n- Vehicle type\n- Vehicle dimensions\n- Availability\nOur team will let you know what additional documents are required."],
        'documents' => ['What documents do I need?',
            "Depending on the opportunity, we may request:\n- Driver's license\n- Proof of insurance\n- Vehicle photos\n- W-9\n- Vehicle information\n- Payment information\n- MC Authority, if applicable\nAdditional documents may be required for certain contracts."],
        'w9' => ['Why do you need a W-9?',
            'The W-9 provides the tax information required to properly process payments and contractor tax reporting.'],
        'mc' => ['Do I need an MC Authority?',
            'Not for every opportunity. Some loads or contracts may require active authority, while others may operate under a different arrangement. Our team will tell you if MC Authority is required.'],
        'how-long' => ['How long does onboarding take?',
            'Once all required information and documents are submitted correctly, our team can review the application. Processing time may vary depending on the route or program.'],
        'submitted' => ['I submitted my information. Does that mean I have a route?',
            'No. Completing onboarding puts you into our system, but a route is not confirmed until LamazonLoads specifically tells you that you have been assigned or scheduled.'],
    ]],
    'routes' => ['Daily routes', 'route', [
        'daily-route' => ['What is a daily route?',
            'A daily route is a scheduled delivery opportunity where a driver picks up packages or freight from a designated location and completes assigned deliveries.'],
        'every-day' => ['Are daily routes guaranteed every day?',
            'Not always. Routes depend on customer demand, contracts, location, performance and availability.'],
        'route-pay' => ['How much do daily routes pay?',
            "Pay depends on the location, contract, vehicle type and route.\nMany advertised opportunities may start around a specific daily rate, but drivers should always confirm the exact rate before accepting a route."],
        'packages' => ['How many packages are on a route?',
            'Package volume varies by route. Some delivery programs may have approximately 60–80 packages or more depending on the day and location.'],
        'stops' => ['How many stops will I have?',
            'The number of stops varies by route and package volume. Exact route information is normally provided when available.'],
        'start-time' => ['What time do routes start?',
            "Pickup times vary by location. Some routes may start around 10:00 AM, 12:00 PM or 2:00 PM.\nAlways follow the pickup time provided for your specific route."],
        'distance' => ['How far will I drive?',
            'Mileage varies by route. Some routes may operate within an approximate delivery radius, while others may cover a larger area.'],
        'details' => ['When will I receive the route details?',
            'Route information is sent once your assignment has been confirmed and the necessary information has been received from the customer or route provider.'],
        'canceled' => ['What happens if a route gets canceled?',
            "Routes and contracts can occasionally be reduced, paused or canceled by the customer.\nIf this happens, LamazonLoads will update the affected drivers and try to provide another opportunity when available."],
        'city' => ['Can I choose which city I want?',
            'Yes. Let our team know which available location works best for you. Placement is still subject to availability.'],
    ]],
    'walmart' => ['Walmart routes', 'cart', [
        'walmart' => ['Does LamazonLoads have Walmart routes?',
            'LamazonLoads may have access to Walmart-related delivery opportunities in select markets when contracts and routes are available.'],
        'walmart-permanent' => ['Are Walmart routes permanent?',
            'Not necessarily. Route availability can change because of volume, customer decisions, performance or contract changes.'],
        'walmart-vehicle' => ['What vehicle do I need for Walmart routes?',
            'Vehicle requirements depend on the specific market. Cargo Vans and Sprinter Vans are commonly preferred for many opportunities.'],
        'walmart-packages' => ['How many Walmart packages will I deliver?',
            'Volume varies by location and day. Some routes may have approximately 60–80 packages, while other routes can have more or fewer.'],
        'seven-days' => ['Can I work seven days a week?',
            'Some routes operate seven days per week, but this does not automatically mean one driver is guaranteed seven days. Scheduling depends on availability and operational needs.'],
        'certain-days' => ['Can I work only certain days?',
            'Tell the support team your availability. We will determine whether the route can accommodate your schedule.'],
    ]],
    'dispatch' => ['Dispatch service', 'headset', [
        'dispatcher' => ['What does a LamazonLoads dispatcher do?',
            "Our dispatch team may:\n- Search for available loads\n- Submit bids\n- Negotiate rates\n- Communicate load information\n- Help coordinate pickup and delivery\n- Keep drivers updated on available opportunities"],
        'fee' => ['What is the dispatch fee?',
            "For applicable dispatched loads, the standard dispatch fee may be 10% of the booked load rate, unless a different agreement applies.\nAlways refer to your individual agreement for the exact terms."],
        'fee-when' => ['When is the dispatch fee charged?',
            "The fee applies according to the driver's dispatch agreement and the loads successfully booked through the service."],
        'no-load' => ["Why haven't I received a load yet?",
            "Load availability depends on factors such as:\n- Your ZIP code\n- Vehicle size\n- Available freight\n- Current market rates\n- Competition from other carriers\n- Deadhead distance\n- Customer requirements\nDispatchers may submit multiple bids before one is accepted."],
        'other-dispatcher' => ['Can another dispatcher or broker help me find loads?',
            'Drivers should follow the terms of their individual agreement. LamazonLoads also believes in helping drivers stay moving, so communication with your assigned support team is important if you have another opportunity.'],
    ]],
    'loads' => ['Loads & bidding', 'chart', [
        'bid' => ["Why wasn't my bid accepted?",
            'Brokers and customers may receive multiple bids. Another carrier may offer a lower rate, have a closer vehicle, meet a specific requirement or be selected for another operational reason.'],
        'rate' => ['Can you guarantee a certain rate per mile?',
            "No. Rates change based on the market, lane, freight, mileage, vehicle type, urgency and customer.\nOur team works to negotiate the strongest reasonable rate available."],
        'deadhead' => ['What is deadhead?',
            'Deadhead is the distance you travel empty before reaching the pickup location.'],
        'without-asking' => ['Will LamazonLoads send me a load without asking?',
            'Drivers should receive the relevant load information before accepting a load. Do not begin a load unless it has been confirmed.'],
        'reject' => ['Can I reject a load?',
            "Yes. If a load doesn't work for you, communicate that with your dispatcher before it is booked or confirmed."],
    ]],
    'payment' => ['Payment', 'dollar', [
        'paid-when' => ['When do drivers get paid?',
            "Payment timing depends on the specific route or contract.\nFor some programs, payments may follow a weekly schedule. Drivers will be informed of the applicable pay period before or during onboarding."],
        'pay-period' => ['How does the weekly pay period work?',
            "For applicable programs, a pay period may run Saturday through Friday, with payment issued according to the established payout schedule.\nSome contracts may use a different schedule."],
        'pay-method' => ['How will I receive payment?',
            "Available payment methods may include:\n- Zelle\n- Direct deposit\n- Other approved payment methods\nThe available method depends on the program."],
        'pay-info' => ['Why do you need my payment information?',
            'We need valid payment information so approved driver payments can be processed correctly.'],
        'paid-immediately' => ['Can I get paid immediately after completing the route?',
            'Not usually. Payments are processed according to the agreed payment cycle for that route or contract.'],
        'payment-missing' => ["What if my payment hasn't arrived?",
            "Contact LamazonLoads support with:\n- Your full name\n- Route/load date\n- Pickup location\n- Amount expected\nOur team can review the payment status."],
    ]],
    'referral' => ['Referral program', 'users', [
        'referral' => ['Does LamazonLoads have a referral program?',
            'Yes. Referral opportunities may be offered for drivers who introduce qualified drivers to LamazonLoads.'],
        'referral-how' => ['How does the referral program work?',
            'You refer a driver to LamazonLoads. The driver must qualify, complete onboarding and successfully complete qualifying work before referral compensation becomes payable.'],
        'referral-amount' => ['How much can I make from a referral?',
            "Referral promotions can vary.\nWhen a referral promotion is active, the exact commission structure will be provided before participation."],
        'referral-number' => ["Do I get paid just for sending someone's phone number?",
            'No. The referred driver generally must meet the requirements of the referral program and complete qualifying work.'],
        'referral-multiple' => ['Can I refer multiple drivers?',
            'Yes. You can refer multiple qualified owner-operators or drivers.'],
    ]],
    'expectations' => ['Driver expectations', 'shield', [
        'late' => ["What happens if I'm late?",
            'Drivers should arrive at the required pickup location on time. Repeated lateness can affect future route assignments.'],
        'no-show' => ["What happens if I accept a route and don't show up?",
            "A no-show can seriously affect the customer, LamazonLoads and other drivers. It may result in removal from that route or future opportunities.\nIf an emergency occurs, contact support immediately."],
        'problem' => ["What should I do if I'm having a problem during a route?",
            "Contact your assigned LamazonLoads support representative or dispatcher immediately. Provide:\n- Your name\n- Route\n- Location\n- What happened\n- Any photos or documentation if necessary"],
        'breakdown' => ['What if my vehicle breaks down?',
            'Notify LamazonLoads immediately. Do not wait until the delivery is already significantly delayed.'],
        'cant-finish' => ["What if I can't finish my route?",
            'Contact management/support immediately so the situation can be addressed with the customer.'],
        'communicate' => ['Do I need to communicate throughout the route?',
            'Yes. Good communication is extremely important. Respond promptly when dispatch or support requests an update.'],
    ]],
    'availability' => ['Availability & support', 'phone', [
        'available' => ["How do I tell LamazonLoads I'm available?",
            "Send your ZIP code + vehicle type + availability.\nExample: 30318 — Cargo Van — Available Today"],
        'update-zip' => ['Should I update my ZIP code when I move?',
            'Yes. Your current location helps dispatch search for loads near you.'],
        'zip-morning' => ['Can I send my ZIP every morning?',
            'Yes. Keeping your location and availability updated helps the team know who is ready to move.'],
        'onboarded-no-load' => ["I'm onboarded but haven't received a load. What should I do?",
            "Send your current:\n- ZIP code\n- Vehicle type\n- Availability\nThis lets the team know you're active and ready for opportunities."],
        'contact' => ['How do I contact LamazonLoads?',
            "Drivers should use the official LamazonLoads communication channels provided during onboarding.\nGeneral contact: info@lamazonloads.com · 678-666-4334"],
        'support-group' => ['Why was I added to a driver support group?',
            'Driver support groups allow LamazonLoads management and support representatives to communicate directly with drivers, provide updates, request availability and assist with issues.'],
        'respond-zip' => ['Should I respond when support asks for my ZIP?',
            'Yes. Sending your current ZIP and equipment type helps the team determine what opportunities may be available near you.'],
        'share' => ['Can drivers share opportunities with each other?',
            'LamazonLoads encourages a driver-focused community. When appropriate, drivers can help one another with legitimate opportunities and useful information.'],
    ]],
    'common' => ['Questions drivers often ask', 'chat', [
        'ready-now' => ["“I'm ready right now. Do you have anything?”",
            "Send us:\n- Current ZIP\n- Vehicle type\n- Vehicle dimensions\n- How far you're willing to travel\nWe can check available opportunities."],
        'not-started' => ["“You told me there was a route. Why haven't I started?”",
            'A route is only officially assigned once LamazonLoads confirms the assignment. Some opportunities are still pending customer approval, final scheduling or driver placement.'],
        'disappeared' => ['“Why did the route disappear?”',
            'Customers can change volume, reduce routes, pause contracts or cancel locations. When this happens, LamazonLoads will communicate available alternatives whenever possible.'],
        'another-route' => ['“Can you put me on another route?”',
            'If another route is available and your vehicle and location meet the requirements, we can review you for reassignment.'],
        'near-zip' => ['“Do you have anything close to my ZIP?”',
            'Send your current ZIP and vehicle type. Opportunities change frequently, so the team can check what is currently available.'],
        'more-routes' => ['“When will you have more routes?”',
            'New routes can open as contracts, markets and delivery volume change. Stay active in the community and keep your ZIP, vehicle type and availability updated.'],
    ]],
];

/** The template drivers send when they're looking for work. */
const FAQ_QUICK_RESPONSE = ['Full Name:', 'Phone:', 'Email:', 'Current ZIP:', 'City/State:', 'Vehicle Type:', 'Vehicle Dimensions:', 'Available Today? YES / NO:', 'Looking For: DAILY ROUTE / OTR / BOTH:'];

/** Answer text as HTML: paragraphs, "- " bullet lists, and clickable email / phone. */
function faq_answer_html(string $text): string
{
    $html = '';
    $list = [];
    $flush = function () use (&$list, &$html): void {
        if ($list) {
            $html .= '<ul class="faq-list">' . implode('', array_map(fn ($li) => '<li>' . $li . '</li>', $list)) . '</ul>';
            $list = [];
        }
    };
    $link = function (string $s): string {
        $s = e($s);
        $s = preg_replace('/\b(info@lamazonloads\.com)\b/i', '<a href="mailto:info@lamazonloads.com">$1</a>', $s);
        return (string) preg_replace_callback('/\b(\d{3}-\d{3}-\d{4})\b/', fn ($m) => '<a href="' . e(tel_href($m[1])) . '">' . $m[1] . '</a>', $s);
    };
    foreach (explode("\n", $text) as $line) {
        if (str_starts_with($line, '- ')) {
            $list[] = $link(substr($line, 2));
            continue;
        }
        $flush();
        $html .= '<p>' . $link($line) . '</p>';
    }
    $flush();
    return $html;
}

/** One FAQ entry by key (for the home page). */
function faq_item(string $key): ?array
{
    foreach (FAQ as [, , $items]) {
        if (isset($items[$key])) {
            return $items[$key];
        }
    }
    return null;
}
