<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$email = (string) config('contact_email') ?: 'info@lamazonloads.com';
$phone = (string) config('contact_phone');
$updated = 'October 6, 2026';
$mail = '<a href="mailto:' . e($email) . '">' . e($email) . '</a>';

$sections = [
    'collect' => ['What we collect', 'clipboard'],
    'use' => ['How we use it', 'route'],
    'share' => ['Who we share it with', 'users'],
    'emails' => ['Emails we send', 'mail'],
    'chat' => ['Live chat', 'chat'],
    'cookies' => ['Cookies', 'shield'],
    'security' => ['How we protect it', 'shield'],
    'keep' => ['How long we keep it', 'clock'],
    'choices' => ['Your choices and rights', 'user'],
    'children' => ['Children', 'users'],
    'changes' => ['Changes to this policy', 'file'],
    'contact' => ['Contact us', 'phone'],
];

page_header('Privacy policy', '', 'How LamazonLoads collects, uses, shares and protects your information: accounts, driver applications, documents, emails, partner requests and live chat.');
page_hero('Privacy', 'Privacy policy', 'Plain and simple: what we collect, why we need it, who sees it and the choices you have.');
?>
<section class="section legal-page">
  <div class="container legal-layout">
    <aside class="legal-side">
      <nav class="faq-nav" aria-label="Privacy policy sections">
        <?php $i = 0; foreach ($sections as $id => [$title, $ic]): $i++; ?>
          <a href="#<?= e($id) ?>" data-legal-link="<?= e($id) ?>"><?= icon($ic) ?><span><?= $i ?>. <?= e($title) ?></span></a>
        <?php endforeach; ?>
      </nav>
      <p class="legal-updated"><?= icon('clock') ?>Last updated <?= e($updated) ?></p>
    </aside>

    <div class="legal-main">
      <div class="glance card pad">
        <h2>At a glance</h2>
        <div class="glance-grid">
          <div><span class="glance-ico"><?= icon('dollar') ?></span><b>We never sell your information</b><small>And we don't use it for advertising.</small></div>
          <div><span class="glance-ico"><?= icon('route') ?></span><b>Used to get you loaded</b><small>Matching, onboarding, dispatch, routes and payment.</small></div>
          <div><span class="glance-ico"><?= icon('shield') ?></span><b>Your documents stay private</b><small>Only you and our staff can open them.</small></div>
          <div><span class="glance-ico"><?= icon('user') ?></span><b>You're in control</b><small>See, fix or delete your information any time.</small></div>
        </div>
        <p class="mb-0 muted">This policy covers lamazonloads.com and the emails and support we provide through it. “LamazonLoads”, “we” and “us” mean LamazonLoads LLC.</p>
      </div>

      <article class="card pad prose legal-body">
        <section id="collect">
          <h2><span>1</span>What we collect</h2>
          <h3>Information you give us</h3>
          <ul>
            <li><b>Your account:</b> name, email, mobile phone, your city and state, what describes you (owner-operator, driver, dispatcher, driver recruiter…), the vehicle types you drive, and a scrambled copy of your password. We can't read your password.</li>
            <li><b>Your driver profile:</b> equipment and vehicle, home ZIP code, service area, availability, years of experience, company name, MC / DOT numbers, insurance company and expiry date, and anything you add in your notes.</li>
            <li><b>Job applications:</b> the openings you apply for, your name, phone, location, vehicles and whether you own, rent or lease them, any Walmart daily route city and daily rate you ask for, your message and, if you add one, your resume.</li>
            <li><b>Documents you upload:</b> such as your W-9, certificate of insurance, driver's license and vehicle registration.</li>
            <li><b>Onboarding:</b> the documents you upload on your onboarding page (vehicle photos, W-9, proof of insurance and a photo of your driver's license), your payment details (how you want to be paid, such as Zelle, Cash App or Apple Pay, the name on the account and the email, phone number or $Cashtag linked to it), and the agreement you sign online: your printed name, signature, company name, the date and time, your IP address and your emergency contact. We never ask for bank account numbers on the website. If you email us documents instead, our staff may add them to your account (marked “Added by LamazonLoads staff”). If our team sends you an onboarding email before you have an account, we keep your email address, the first name we used, which email we sent (and the Walmart route city), and when.</li>
            <li><b>Availability updates:</b> the ZIP code, vehicle type, dimensions and availability you send to dispatch or support.</li>
            <li><b>Messages:</b> what you send through the contact form, the live chat or by email.</li>
            <li><b>Partner requests:</b> when a business asks for a call: the contact's name, company, job title, email, phone, location, delivery volume, best time to call and message.</li>
          </ul>
          <h3>Information collected automatically</h3>
          <ul>
            <li><b>Security records:</b> your IP address and the time of sign-in attempts, password-reset requests and form submissions, used to block spam and break-in attempts.</li>
            <li><b>Cookies:</b> see <a href="#cookies">Cookies</a>. We don't use analytics, advertising or tracking tools.</li>
          </ul>
        </section>

        <section id="use">
          <h2><span>2</span>How we use it</h2>
          <p>Only to run LamazonLoads:</p>
          <ul>
            <li>Match you with loads, daily routes, Walmart routes and job openings that fit your vehicle and location.</li>
            <li>Review applications, onboard drivers and confirm route assignments.</li>
            <li>Dispatch: search, bid on and book loads for you, and coordinate pickup and delivery.</li>
            <li>Pay you, handle referral payouts and keep the tax records the law requires (for example from your W-9).</li>
            <li>Answer your questions and send the emails described in <a href="#emails">Emails we send</a>.</li>
            <li>Keep the website and your account secure.</li>
          </ul>
          <p>We <b>don't sell</b> your information, we don't “share” it for targeted advertising, and we don't use it for advertising of any kind.</p>
        </section>

        <section id="share">
          <h2><span>3</span>Who we share it with</h2>
          <ul>
            <li><b>LamazonLoads staff</b> who need it to dispatch, onboard, support or pay you.</li>
            <li><b>Brokers, shippers and route customers:</b> when we book a load or place you on a route (including Walmart-related routes), we share only what that job needs, such as your name, phone, vehicle, company name, MC / DOT number and insurance. This is normal in dispatching.</li>
            <li><b>Telegram:</b> when you finish onboarding, we invite you to our driver group on Telegram. What you share there is also covered by Telegram's own privacy policy.</li>
            <li><b>Our service providers:</b> Hostinger hosts our website, database and email. They process information only to provide that service to us.</li>
            <li><b>Payment:</b> your Zelle or bank details are used only to send you money through that payment service.</li>
            <li><b>When the law requires it:</b> for example to comply with tax rules or a valid legal request, or to protect drivers, customers or LamazonLoads from fraud or harm.</li>
            <li><b>If the business changes hands:</b> information may transfer to a new owner, who must protect it the same way.</li>
          </ul>
        </section>

        <section id="emails">
          <h2><span>4</span>Emails we send</h2>
          <p>All our emails come from <b><?= e($email) ?></b>, and you can reply to any of them.</p>
          <ul>
            <li><b>Account emails:</b> if our staff create your account for you, your sign-in details (you choose your own password the first time you sign in); confirming your email address, password reset links, “your password was changed” notices, and a confirmation when your account is deleted or closed.</li>
            <li><b>Application and onboarding emails:</b> right after you apply, or when our team sends it to you directly, our Dispatch Services email or our Walmart Daily Route welcome email with a link to upload your documents; then the result of our review, a link to sign your agreement, and your Telegram invitation.</li>
            <li><b>Replies:</b> answers to your chat messages, contact form or partner request.</li>
          </ul>
          <p>We don't send newsletters or marketing emails from the website. If you'd like us to stop emailing you, just reply and tell us; we'll still send emails that are needed to keep your account secure.</p>
        </section>

        <section id="chat">
          <h2><span>5</span>Live chat</h2>
          <p>When you use the <b>Chat</b> button, your messages go only to LamazonLoads staff. They're stored on our website and are not shared with any other chat service.</p>
          <ul>
            <li>If you're not signed in, we ask for your name and email only so we can reply, including by email if you've left the website.</li>
            <li>To remember your chat on this browser, we set a cookie with a random number. We store only a scrambled copy of it, so it can't be linked to you by anyone else.</li>
            <li>If you close the “Need help?” note, your browser remembers that for a day (it stays on your device).</li>
            <li>If you rate a chat, we keep the stars and your comment with that chat.</li>
          </ul>
        </section>

        <section id="cookies">
          <h2><span>6</span>Cookies</h2>
          <p>We use only cookies the website needs to work. There are no advertising, analytics or tracking cookies.</p>
          <div class="table-wrap"><table class="legal-table">
            <thead><tr><th>Cookie</th><th>What it does</th><th>How long</th></tr></thead>
            <tbody>
              <tr><td>Sign-in</td><td>Keeps you signed in and protects your forms</td><td>Until you close your browser (up to 12 hours of inactivity)</td></tr>
              <tr><td>Chat</td><td>Remembers your chat on this browser</td><td>Up to 1 year</td></tr>
            </tbody>
          </table></div>
        </section>

        <section id="security">
          <h2><span>7</span>How we protect it</h2>
          <ul>
            <li>The whole website uses a secure HTTPS connection.</li>
            <li>Passwords are stored scrambled. Password reset links work once, for one hour.</li>
            <li>Uploaded documents can't be opened from the internet; only you and signed-in staff can see them.</li>
            <li>Only staff accounts can open the admin area.</li>
            <li>Sign-in attempts and forms are limited to stop guessing and spam.</li>
          </ul>
          <p>No website is 100% secure. If you think your account has been misused, contact us right away.</p>
        </section>

        <section id="keep">
          <h2><span>8</span>How long we keep it</h2>
          <div class="table-wrap"><table class="legal-table">
            <thead><tr><th>Information</th><th>How long</th></tr></thead>
            <tbody>
              <tr><td>Account, profile, applications, uploaded documents, payment details and signed agreements</td><td>While you have an account. You can delete your whole account yourself any time, or ask us to remove a document.</td></tr>
              <tr><td>Live chats</td><td>Deleted after 180 days without new messages, or when your account is deleted</td></tr>
              <tr><td>Sign-in attempts and spam-protection records</td><td>About 1 day</td></tr>
              <tr><td>Partner requests and contact messages</td><td>As long as needed to respond and follow up</td></tr>
              <tr><td>Payment and tax records (such as W-9s)</td><td>As long as tax and business laws require</td></tr>
            </tbody>
          </table></div>
        </section>

        <section id="choices">
          <h2><span>9</span>Your choices and rights</h2>
          <ul>
            <li><b>See and update:</b> change your account, profile and documents any time in your dashboard.</li>
            <li><b>Get a copy:</b> ask us for a copy of the information we have about you.</li>
            <li><b>Correct:</b> ask us to fix anything that's wrong.</li>
            <li><b>Delete:</b> delete your account and everything in it yourself under <b>Account settings → Delete my account</b>, or ask us to do it. We may keep records the law requires (such as tax records).</li>
            <li><b>Stop emails:</b> reply to any email and tell us.</li>
          </ul>
          <p>Some states (such as California, Virginia and Colorado) give residents specific privacy rights. We honor these requests for everyone, wherever you live, and we'll never treat you differently for using them. Email <?= $mail ?> and we'll reply within 30 days. We may ask you to confirm it's really you first.</p>
        </section>

        <section id="children">
          <h2><span>10</span>Children</h2>
          <p>LamazonLoads is for adults looking for driving and freight work. We don't knowingly collect information from anyone under 18. If you believe a child has given us information, contact us and we'll delete it.</p>
        </section>

        <section id="changes">
          <h2><span>11</span>Changes to this policy</h2>
          <p>We'll update this page when our practices change and show the new date at the top. If a change is important, we'll also let account holders know by email.</p>
        </section>

        <section id="contact">
          <h2><span>12</span>Contact us</h2>
          <p>Questions or requests about your information:</p>
          <ul class="legal-contact">
            <li><?= icon('mail') ?><?= $mail ?></li>
            <?php if ($phone !== ''): ?><li><?= icon('phone') ?><a href="<?= e(tel_href($phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
            <li><?= icon('chat') ?>The Chat button on any page</li>
          </ul>
        </section>
      </article>
    </div>
  </div>
</section>
<?php page_footer();
