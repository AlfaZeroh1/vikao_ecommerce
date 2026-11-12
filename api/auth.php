<?php
require __DIR__ . '/_init.php';

route([
    'login' => function () {
        $email = (string)input('email', ''); $pass = (string)($_POST['password'] ?? '');
        if (!rate_limit('login:' . client_ip(), 10, 600)) json_fail('Too many attempts. Try again in 10 minutes.', 429);
        $err = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err['email'] = 'Enter a valid email.';
        if ($pass === '') $err['password'] = 'Enter your password.';
        if ($err) json_fail('Please check your details.', 422, $err);
        $u = attempt_login($email, $pass);
        if (!$u) json_fail('Email or password is incorrect.', 422, ['password' => 'Email or password is incorrect.']);
        login_user((int)$u['id']);
        $next = (string)input('next', '');
        json_ok(['redirect' => ($next && str_starts_with($next, '/') && !str_starts_with($next, '//')) ? $next : intended_url()], 'Welcome back, ' . $u['first_name'] . '!');
    },

    'register' => function () {
        if (!rate_limit('register:' . client_ip(), 6, 3600)) json_fail('Too many sign-ups from this network. Try later.', 429);
        $first = trim((string)input('first_name', '')); $last = trim((string)input('last_name', ''));
        $email = strtolower(trim((string)input('email', '')));
        $phone = normalize_phone((string)input('phone', ''));
        $pass = (string)($_POST['password'] ?? ''); $pass2 = (string)($_POST['password_confirmation'] ?? '');
        $err = [];
        if (mb_strlen($first) < 2) $err['first_name'] = 'Enter your first name.';
        if (mb_strlen($last) < 2) $err['last_name'] = 'Enter your last name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err['email'] = 'Enter a valid email address.';
        elseif (val("SELECT 1 FROM users WHERE email = ?", [$email])) $err['email'] = 'An account with this email already exists.';
        if (!$phone) $err['phone'] = 'Enter a valid phone number, e.g. 0712 345 678.';
        elseif (val("SELECT 1 FROM users WHERE phone = ?", [$phone])) $err['phone'] = 'This phone number is already registered.';
        if (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) $err['password'] = 'Use at least 8 characters with letters and numbers.';
        if ($pass !== $pass2) $err['password_confirmation'] = 'Passwords do not match.';
        if (empty($_POST['terms'])) $err['terms'] = 'Please accept the terms to continue.';
        if ($err) json_fail('Please fix the highlighted fields.', 422, $err);
        $id = create_user(['first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone' => $phone, 'password' => $pass]);
        login_user($id);
        json_ok(['redirect' => intended_url()], 'Account created. Karibu Vikao, ' . $first . '!');
    },

    'logout' => function () {
        logout_user();
        json_ok(['redirect' => url()], 'Signed out.');
    },

    // Placeholder so the UI can show OAuth buttons now; wire Google/Apple/Facebook SDKs later.
    'oauth' => function () {
        $p = (string)input('provider');
        if (!in_array($p, ['google', 'apple', 'facebook'], true)) json_fail('Unknown provider.');
        json_fail(ucfirst($p) . ' sign-in is coming soon. Use email for now.', 501);
    },
]);
