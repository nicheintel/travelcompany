<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$email = (string) config('contact_email');
page_header('Privacy policy', '', 'How LamazonLoads handles your information: accounts, driver documents, job applications, contact messages, partner requests and the live chat.');
page_hero('Privacy', 'Privacy policy', 'Plain and simple: what we collect, why, and what we do with it.');
?>
<section class="section">
  <div class="container narrow">
    <div class="card pad prose">
      <p class="muted">Last updated: <?= e(date('F j, Y', strtotime('2026-10-05'))) ?></p>

      <h2>What we collect</h2>
      <ul>
        <li><b>Your account:</b> name, email, phone number, what describes you (owner-operator, driver, dispatcher…) and a scrambled copy of your password (we can't read it).</li>
        <li><b>Your driver profile:</b> equipment, vehicle, home ZIP code, availability, MC / DOT numbers, insurance company and expiry date, and anything you add in the notes.</li>
        <li><b>Your documents:</b> files you upload, such as your W-9, insurance certificate and driver's license.</li>
        <li><b>Your applications:</b> the openings you apply for, your name, phone number, location, vehicle and whether you own, rent or lease it, any Walmart daily route city and pay rate you choose, and the message you send.</li>
        <li><b>Contact form:</b> your name, email, phone (optional) and message.</li>
        <li><b>Partner requests:</b> when a business asks for a call, the contact's name, company, job title, email, phone and the details shared about their deliveries.</li>
        <li><b>Live chat:</b> see "Live chat" below.</li>
      </ul>

      <h2>Why we use it</h2>
      <p>Only to run LamazonLoads: to match you with loads, routes and job openings, to onboard you, to answer your questions and to contact you about your applications. We don't sell your information and we don't use it for advertising.</p>

      <h2>Who can see it</h2>
      <p>You and LamazonLoads staff. Your documents are private: they can't be opened from the internet, only by you and our staff after signing in. When we book a load or route for you, we share only what that job needs (such as your company name, MC / DOT number and insurance) with the broker or customer, as is normal in dispatching.</p>

      <h2>Live chat</h2>
      <p>When you use the <b>Chat</b> button, your messages go only to LamazonLoads staff. They are stored on our website and are not shared with any other company or chat service.</p>
      <ul>
        <li>If you're not signed in, we ask for your name and email only so we can reply, including by email if you've left the website.</li>
        <li>To remember your chat on this browser, we set a cookie with a random number. We store only a scrambled copy of it, so it can't be linked to you by anyone else.</li>
        <li>If you close the "Need help?" note, your browser remembers that for a day (it stays on your device).</li>
        <li>Chats are deleted automatically after 180 days without new messages, and when your account is deleted.</li>
        <li>If you rate a chat, we keep the stars and comment with that chat.</li>
      </ul>

      <h2>Cookies</h2>
      <p>We use only the cookies the website needs: one that keeps you signed in, and the chat cookie above. No advertising or tracking cookies.</p>

      <h2>Where it's stored</h2>
      <p>Our website and its database are hosted by Hostinger. Emails from us (such as chat replies) are sent through our own mailbox.</p>

      <h2>How long we keep it</h2>
      <p>Your account, profile, documents and applications are kept while you have an account with us. You can remove documents yourself at any time from your dashboard. Chats are deleted as described above.</p>

      <h2>Your choices</h2>
      <p>You can see and change your details in your dashboard. To get a copy of your information or to have your account and everything in it deleted, email us<?= $email !== '' ? ' at <a href="mailto:' . e($email) . '">' . e($email) . '</a>' : '' ?>.</p>
    </div>
  </div>
</section>
<?php page_footer();
