<?php
/**
 * Authentication. Email+password today; OAuth (Google/Apple/Facebook) plugs into
 * user_oauth_accounts via auth_login_oauth() without schema changes.
 */

function current_user(): ?array {
    static $u = false;
    if ($u !== false) return $u;
    $id = $_SESSION['uid'] ?? null;
    if (!$id) return $u = null;
    $u = one("SELECT u.*, t.code AS tier_code, t.name AS tier_name, t.color_hex AS tier_color, t.icon AS tier_icon
              FROM users u LEFT JOIN membership_tiers t ON t.id = u.membership_tier_id
              WHERE u.id = ? AND u.deleted_at IS NULL AND u.status = 'active'", [$id]);
    if (!$u) { unset($_SESSION['uid']); return $u = null; }
    if (empty($_SESSION['seen']) || $_SESSION['seen'] < time() - 120) {
        q("UPDATE users SET last_seen_at = NOW() WHERE id = ?", [$u['id']]);
        $_SESSION['seen'] = time();
    }
    return $u;
}
function uid(): ?int { $u = current_user(); return $u ? (int)$u['id'] : null; }
function is_logged_in(): bool { return current_user() !== null; }
function is_staff(): bool { $u = current_user(); return $u && in_array($u['role'], ['staff', 'admin'], true); }

function require_login(): array {
    $u = current_user();
    if ($u) return $u;
    if (is_ajax() || str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) json_fail('Please sign in to continue.', 401, ['login' => url('login.php')]);
    $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? url();
    flash('info', 'Please sign in to continue.');
    redirect('login.php');
}

function login_user(int $userId): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = $userId;
    q("UPDATE users SET last_login_at = NOW(), last_seen_at = NOW() WHERE id = ?", [$userId]);
    log_activity($userId, 'auth.login');
}
function logout_user(): void {
    log_activity(uid(), 'auth.logout');
    $_SESSION = [];
    session_regenerate_id(true);
}
function intended_url(string $fallback = 'index.php'): string {
    $u = $_SESSION['intended'] ?? null; unset($_SESSION['intended']);
    return $u ?: url($fallback);
}

/**
 * Create an account. $data: first_name,last_name,email,phone,password (nullable for OAuth)
 * Returns new user id. Randomly assigns membership tier + badges for the MVP.
 */
function create_user(array $data, string $accountType = 'normal', string $role = 'user'): int {
    return tx(function () use ($data, $accountType, $role) {
        $first = trim($data['first_name']); $last = trim($data['last_name'] ?? '');
        $id = insert('users', [
            'uuid' => uuid4(),
            'account_type' => $accountType,
            'role' => $role,
            'first_name' => $first,
            'last_name' => $last ?: null,
            'display_name' => $data['display_name'] ?? trim("$first $last"),
            'email' => strtolower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            'phone_country_id' => 1,
            'password_hash' => !empty($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : null,
            'avatar_url' => $data['avatar_url'] ?? null,
            'membership_tier_id' => $data['membership_tier_id'] ?? random_int(1, 3),
            'membership_expires_at' => date('Y-m-d H:i:s', strtotime('+1 year')),
            'email_verified_at' => $data['email_verified_at'] ?? null,
        ]);
        // MVP: random badges (verified ~60%, popular ~35%)
        if ($accountType === 'official') {
            q("INSERT IGNORE INTO user_badges (user_id, badge_id) VALUES (?, 1), (?, 3)", [$id, $id]);
        } else {
            if (random_int(1, 100) <= 60) q("INSERT IGNORE INTO user_badges (user_id, badge_id) VALUES (?, 1)", [$id]);
            if (random_int(1, 100) <= 35) q("INSERT IGNORE INTO user_badges (user_id, badge_id) VALUES (?, 2)", [$id]);
        }
        notify($id, 'welcome', 'Welcome to Vikao 🎉', 'Your account is ready. Post your first ad or start exploring homes, land and spaces.', 'fa-hand-sparkles', url('sell.php'));
        return $id;
    });
}

function attempt_login(string $email, string $password): ?array {
    $u = one("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL", [strtolower(trim($email))]);
    if (!$u || !$u['password_hash'] || !password_verify($password, $u['password_hash'])) return null;
    if ($u['status'] !== 'active') return null;
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    return $u;
}

/**
 * Future: called by /auth/{provider}/callback after verifying the provider's ID token.
 * Links to an existing account by email, or creates a new password-less one.
 */
function auth_login_oauth(string $provider, string $providerUserId, string $email, string $first, ?string $last = null, ?string $avatar = null, array $raw = []): int {
    $link = one("SELECT user_id FROM user_oauth_accounts WHERE provider = ? AND provider_user_id = ?", [$provider, $providerUserId]);
    if ($link) { login_user((int)$link['user_id']); return (int)$link['user_id']; }
    $userId = (int)(val("SELECT id FROM users WHERE email = ?", [strtolower($email)]) ?? 0);
    if (!$userId) $userId = create_user(['first_name' => $first, 'last_name' => $last, 'email' => $email, 'avatar_url' => $avatar, 'email_verified_at' => date('Y-m-d H:i:s')]);
    insert('user_oauth_accounts', ['user_id' => $userId, 'provider' => $provider, 'provider_user_id' => $providerUserId, 'provider_email' => $email, 'raw_profile' => json_encode($raw)]);
    login_user($userId);
    return $userId;
}

function user_badges(int $userId): array {
    static $cache = [];
    return $cache[$userId] ??= all("SELECT b.* FROM user_badges ub JOIN badges b ON b.id = ub.badge_id
        WHERE ub.user_id = ? AND (ub.expires_at IS NULL OR ub.expires_at > NOW()) ORDER BY b.id", [$userId]);
}
function official_user_id(): ?int {
    static $id = false;
    if ($id === false) $id = ($v = val("SELECT id FROM users WHERE account_type = 'official' ORDER BY id LIMIT 1")) ? (int)$v : null;
    return $id;
}
