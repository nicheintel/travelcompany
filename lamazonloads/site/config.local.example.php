<?php
// LamazonLoads settings. On Hostinger this file is public_html/config.local.php.
// Replace every PUT_... value, then save. See HOSTINGER.md, step 4.
return [
    // Database from hPanel -> Databases -> Management (full names start with u + numbers)
    'db_host' => 'localhost',
    'db_name' => 'PUT_DATABASE_NAME_HERE',
    'db_user' => 'PUT_DATABASE_USER_HERE',
    'db_pass' => 'PUT_DATABASE_PASSWORD_HERE',

    // Your email: signing up with it gives you the Admin dashboard. Several: 'a@x.com, b@x.com'
    'admin_emails' => 'PUT_YOUR_EMAIL_HERE',

    // Shown in the footer and on the Contact page
    'contact_email' => 'info@lamazonloads.com',
    'contact_phone' => '(678) 528-1181',

    // Email for the live chat (alerts to you, replies to visitors). Use your Hostinger mailbox:
    // hPanel -> Emails -> info@lamazonloads.com. Put that mailbox's password here.
    'smtp_user' => 'info@lamazonloads.com',
    'smtp_pass' => 'PUT_EMAIL_PASSWORD_HERE',
];
