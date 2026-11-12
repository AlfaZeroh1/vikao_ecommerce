<?php
/**
 * Vikao — configuration. Copy to config.php and adjust.
 */
return [
    'app' => [
        'name'      => 'Vikao',
        'tagline'   => 'Homes · Land · Spaces',
        'url'       => 'http://localhost/vikao',   // no trailing slash. Used in emails.
        'base_path' => null,                       // null = auto-detect (e.g. "/vikao"); '' when served at domain root
        'env'       => 'local',                    // local | production
        'timezone'  => 'Africa/Nairobi',
        'currency'  => 'KES',
        'support_email' => 'hello@vikao.io',
        'support_phone' => '+254 700 000 000',
    ],
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'vikao',
        'user'    => 'nario',
        'pass'    => 'nariokotome',
        'charset' => 'utf8mb4',
    ],
    'search' => [
        'driver' => 'fulltext',   // fulltext (MySQL FULLTEXT) | like (portable fallback)
        'per_page' => 24,
    ],
    'uploads' => [
        'dir'        => __DIR__ . '/../uploads/listings',
        'url'        => 'uploads/listings',
        'max_bytes'  => 5 * 1024 * 1024,
        'min_photos' => 6,
        'max_photos' => 20,
        'mimes'      => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
        'thumb_width'=> 640,
    ],
    'listing' => [
        'title_max'       => 120,
        'description_max' => 5000,   // plain-text characters
        'expiry_days'     => 60,     // normal ads expire after N days (official ads never)
    ],
    'mail' => [
        'driver'    => 'simulate',   // simulate (store in email_outbox) | mail (PHP mail())
        'from'      => 'no-reply@vikao.io',
        'from_name' => 'Vikao',
    ],
    'payments' => [
        'mpesa' => [
            'simulate'        => true,   // false once Daraja is wired in
            'shortcode'       => '174379',
            'consumer_key'    => '',
            'consumer_secret' => '',
            'passkey'         => '',
            'callback_url'    => 'https://vikao.io/api/payments.php?action=callback',
        ],
    ],
    'pms' => [
        // Mock PMS gate. Staff/admin users can also sign in with their account.
        'passcode' => 'vikao-pms-2026',
        'webhook'  => null,   // future: real PMS endpoint that receives pms_events
    ],
];
