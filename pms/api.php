<?php
/** Mock PMS AJAX controller. */
require __DIR__ . '/../api/_init.php';
require __DIR__ . '/_gate.php';
if (!is_logged_in() || !pms_allowed()) json_fail('PMS access required.', 403);
$reviewer = current_user();

function pms_listing(): array {
    $l = find_listing((string)input('listing'));
    if (!$l) json_fail('Listing not found.', 404);
    return $l;
}

route([
    'approve' => function () use ($reviewer) {
        $l = pms_listing();
        if ($l['status'] !== 'pending') json_fail('This ad is no longer pending.');
        approve_listing((int)$l['id'], (int)$reviewer['id']);
        log_activity((int)$reviewer['id'], 'pms.approve', 'listing', (int)$l['id']);
        json_ok([], 'Approved — "' . excerpt($l['title'], 40) . '" is live.');
    },
    'reject' => function () use ($reviewer) {
        $l = pms_listing();
        $reason = trim(mb_substr((string)input('reason', ''), 0, 500));
        if (mb_strlen($reason) < 5) json_fail('Give the seller a reason.');
        reject_listing((int)$l['id'], (int)$reviewer['id'], $reason);
        log_activity((int)$reviewer['id'], 'pms.reject', 'listing', (int)$l['id'], ['reason' => $reason]);
        json_ok([], 'Declined and the seller was notified.');
    },
    'ack' => function () {
        q("UPDATE pms_events SET status = 'acknowledged', acknowledged_at = NOW() WHERE id = ?", [input_int('id', 0)]);
        $ev = one("SELECT * FROM pms_events WHERE id = ?", [input_int('id', 0)]);
        if ($ev && $ev['aggregate_type'] === 'order') q("UPDATE orders SET pms_sync_status = 'acknowledged', pms_reference = COALESCE(pms_reference, ?) WHERE id = ?", ['PMS-' . $ev['id'], $ev['aggregate_id']]);
        json_ok();
    },
    'post_official' => function () {
        $official = official_user_id();
        if (!$official) json_fail('No official account exists. Run the installer seed.');
        $owner = one("SELECT * FROM users WHERE id = ?", [$official]);
        $existing = null;
        if ($uuid = (string)input('listing', '')) {
            $existing = find_listing($uuid);
            if (!$existing || !$existing['is_official']) json_fail('Official listing not found.', 404);
        }
        $l = save_listing($_POST, $owner, ['existing' => $existing, 'official' => true, 'files' => uploaded_files('photos'), 'remove_media' => input_array('remove_media')]);
        if (!$existing) q("UPDATE listings SET published_at = NOW() WHERE id = ?", [$l['id']]);
        json_ok(['listing' => $l['uuid'], 'redirect' => url('ad.php', ['id' => $l['uuid']])], $existing ? 'Official listing updated.' : 'Published to the Official Vikao Store.');
    },
    'toggle_official' => function () {
        $l = pms_listing();
        if (!$l['is_official']) json_fail('Not an official listing.');
        $to = $l['status'] === 'active' ? 'closed' : 'active';
        q("UPDATE listings SET status = ?, closed_at = IF(? = 'closed', NOW(), NULL) WHERE id = ?", [$to, $to, $l['id']]);
        json_ok(['status' => $to], $to === 'active' ? 'Listing published.' : 'Listing unpublished.');
    },
]);
