<?php
require __DIR__ . '/_init.php';

/** owner-scoped listing lookup */
function own_listing(array $u): array {
    $l = find_listing((string)input('listing'));
    if (!$l || (int)$l['user_id'] !== (int)$u['id']) json_fail('Listing not found.', 404);
    return $l;
}

route([
    /* Shop page AJAX: returns rendered cards + pagination + counts */
    'search' => function () {
        $f = shop_filters();
        $res = search_listings($f);
        $favs = favourite_ids(uid());
        $layout = input('layout') === 'list' ? 'list' : 'grid';
        $html = '';
        foreach ($res['rows'] as $l) $html .= render_component('ad_card', ['l' => $l, 'favs' => $favs, 'layout' => $layout]);
        json_ok([
            'html' => $html, 'total' => $res['total'], 'page' => $res['page'], 'pages' => $res['pages'],
            'pagination' => render_component('pagination', ['page' => $res['page'], 'pages' => $res['pages']]),
        ]);
    },

    /* Create or edit (multipart/form-data) */
    'save' => function () {
        $u = require_login();
        if (!rate_limit('post:' . $u['id'], 30, 3600)) json_fail('You are posting too fast. Try again later.', 429);
        $existing = null;
        if ($uuid = (string)input('listing', '')) {
            $existing = find_listing($uuid);
            if (!$existing || (int)$existing['user_id'] !== (int)$u['id']) json_fail('Listing not found.', 404);
            if ($existing['is_official']) json_fail('Official listings are managed from the PMS.', 403);
        } elseif ($u['tier_code'] ?? null) {
            $max = val("SELECT max_active_ads FROM membership_tiers WHERE code = ?", [$u['tier_code']]);
            $active = (int)val("SELECT COUNT(*) FROM listings WHERE user_id = ? AND status IN ('active','pending') AND deleted_at IS NULL", [$u['id']]);
            if ($max !== null && $active >= (int)$max) json_fail("Your {$u['tier_name']} plan allows $max active ads. Close an ad or upgrade.", 403);
        }
        $in = $_POST;
        $listing = save_listing($in, $u, [
            'existing' => $existing, 'official' => false,
            'files' => uploaded_files('photos'),
            'remove_media' => input_array('remove_media'),
        ]);
        json_ok(['listing' => $listing['uuid'], 'redirect' => url('my-listings.php', ['submitted' => $listing['uuid']])],
            $existing ? 'Changes submitted — we\'re reviewing your ad.' : 'Success! We\'re reviewing your ad.');
    },

    'close' => function () {
        $u = require_login(); $l = own_listing($u);
        $reason = mb_substr((string)input('reason', 'Closed by owner'), 0, 120);
        $status = input('sold') === '1' ? 'sold' : 'closed';
        q("UPDATE listings SET status = ?, close_reason = ?, closed_at = NOW() WHERE id = ?", [$status, $reason, $l['id']]);
        log_activity((int)$u['id'], 'listing.close', 'listing', (int)$l['id'], ['reason' => $reason]);
        json_ok(['status' => $status], 'Ad closed.');
    },

    'reopen' => function () {
        $u = require_login(); $l = own_listing($u);
        if (!in_array($l['status'], ['closed', 'sold', 'expired', 'rejected'], true)) json_fail('This ad is already open.');
        tx(function () use ($l) {
            q("UPDATE listings SET status = 'pending', submitted_at = NOW(), closed_at = NULL, close_reason = NULL WHERE id = ?", [$l['id']]);
            insert('listing_reviews', ['listing_id' => (int)$l['id'], 'submission_type' => 'resubmit', 'snapshot' => json_encode(['title' => $l['title']])]);
            pms_event('listing.resubmitted', 'listing', (int)$l['id'], ['listing_id' => (int)$l['id'], 'title' => $l['title']]);
        });
        json_ok(['status' => 'pending'], 'Re-submitted for review.');
    },

    'delete' => function () {
        $u = require_login(); $l = own_listing($u);
        q("UPDATE listings SET deleted_at = NOW(), status = 'closed' WHERE id = ?", [$l['id']]);
        json_ok([], 'Ad deleted.');
    },

    'reveal_phone' => function () {
        $l = find_listing((string)input('listing'));
        if (!$l || $l['status'] !== 'active') json_fail('Listing not available.', 404);
        $seller = one("SELECT phone, display_name FROM users WHERE id = ?", [$l['user_id']]);
        if (!$seller['phone']) json_fail('The seller has not shared a phone number. Start a chat instead.');
        if (empty($_SESSION['revealed'][$l['id']])) {
            $_SESSION['revealed'][$l['id']] = 1;
            q("UPDATE listings SET phone_reveals_count = phone_reveals_count + 1 WHERE id = ?", [$l['id']]);
            q("INSERT INTO listing_stats_daily (listing_id, stat_date, phone_reveals) VALUES (?, CURDATE(), 1) ON DUPLICATE KEY UPDATE phone_reveals = phone_reveals + 1", [$l['id']]);
        }
        $pretty = preg_replace('/^\+254(\d{3})(\d{3})(\d{3})$/', '0$1 $2 $3', $seller['phone']);
        json_ok(['phone' => $seller['phone'], 'pretty' => $pretty]);
    },

    'report' => function () {
        $l = find_listing((string)input('listing'));
        if (!$l) json_fail('Listing not found.', 404);
        $reason = (string)input('reason');
        if (!in_array($reason, ['scam', 'wrong_category', 'sold', 'duplicate', 'offensive', 'wrong_price', 'other'], true)) json_fail('Choose a reason.');
        if (!rate_limit('report:' . client_ip(), 10, 3600)) json_fail('Too many reports. Try later.', 429);
        insert('listing_reports', ['listing_id' => (int)$l['id'], 'reporter_id' => uid(), 'reason' => $reason, 'details' => mb_substr((string)input('details', ''), 0, 1000) ?: null]);
        json_ok([], 'Thanks — our trust & safety team will review this ad.');
    },
]);
