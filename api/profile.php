<?php
require __DIR__ . '/_init.php';

route([
    'theme' => function () {
        $t = (string)input('theme');
        if (!in_array($t, ['system', 'light', 'dark'], true)) json_fail('Invalid theme.');
        if ($u = current_user()) q("UPDATE users SET theme = ? WHERE id = ?", [$t, $u['id']]);
        json_ok();
    },
    'update' => function () {
        $u = require_login();
        $first = trim((string)input('first_name', '')); $last = trim((string)input('last_name', ''));
        $display = trim((string)input('display_name', '')) ?: trim("$first $last");
        $phone = normalize_phone((string)input('phone', ''));
        $bio = mb_substr(trim((string)input('bio', '')), 0, 500);
        $loc = input_int('area_id') ?: input_int('subcounty_id') ?: input_int('county_id');
        $err = [];
        if (mb_strlen($first) < 2) $err['first_name'] = 'Enter your first name.';
        if (!$phone) $err['phone'] = 'Enter a valid phone number.';
        elseif (val("SELECT 1 FROM users WHERE phone = ? AND id <> ?", [$phone, $u['id']])) $err['phone'] = 'This phone number is used by another account.';
        if (mb_strlen($display) > 120) $err['display_name'] = 'Display name is too long.';
        if ($err) json_fail('Please fix the highlighted fields.', 422, $err);
        $changedPhone = $phone !== $u['phone'];
        update('users', [
            'first_name' => $first, 'last_name' => $last ?: null, 'display_name' => $display, 'phone' => $phone,
            'bio' => $bio ?: null, 'location_id' => $loc ?: null,
            'phone_verified_at' => $changedPhone ? null : $u['phone_verified_at'],
        ], 'id = :id', ['id' => $u['id']]);
        json_ok([], 'Profile updated.');
    },
    'password' => function () {
        $u = require_login();
        $cur = (string)($_POST['current_password'] ?? ''); $new = (string)($_POST['new_password'] ?? ''); $conf = (string)($_POST['new_password_confirmation'] ?? '');
        $err = [];
        if ($u['password_hash'] && !password_verify($cur, $u['password_hash'])) $err['current_password'] = 'Current password is incorrect.';
        if (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) $err['new_password'] = 'Use at least 8 characters with letters and numbers.';
        if ($new !== $conf) $err['new_password_confirmation'] = 'Passwords do not match.';
        if ($err) json_fail('Please fix the highlighted fields.', 422, $err);
        q("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        log_activity((int)$u['id'], 'auth.password_changed');
        json_ok([], 'Password changed.');
    },
]);
