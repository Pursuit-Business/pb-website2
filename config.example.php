<?php
// Copy this file to config.php on the server and fill in real values.
// config.php is gitignored and must never be committed.

return [
    'smtp_host' => 'mail.pursuitbusiness.com.au',
    'smtp_port' => 465,
    'smtp_secure' => 'ssl',                              // 'ssl' for 465, 'tls' for 587
    'smtp_user' => 'noreply@pursuitbusiness.com.au',     // a real mailbox on the domain
    'smtp_pass' => 'CHANGE_ME',                           // that mailbox's password
    'from_email' => 'noreply@pursuitbusiness.com.au',    // should match smtp_user
    'from_name' => 'Pursuit Business Solutions',
    'to_email' => 'terence.chia@gmail.com',              // where leads are delivered
];
