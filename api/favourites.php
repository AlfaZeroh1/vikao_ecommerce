<?php
require __DIR__ . '/_init.php';

route([
    'toggle' => function () {
        $u = require_login();
        $l = find_listing((string)input('listing'));
        if (!$l) json_fail('Listing not found.', 404);
        $exists = val("SELECT 1 FROM favourites WHERE user_id = ? AND listing_id = ?", [$u['id'], $l['id']]);
        if ($exists) {
            q("DELETE FROM favourites WHERE user_id = ? AND listing_id = ?", [$u['id'], $l['id']]);
            q("UPDATE listings SET favourites_count = GREATEST(favourites_count - 1, 0) WHERE id = ?", [$l['id']]);
        } else {
            q("INSERT IGNORE INTO favourites (user_id, listing_id) VALUES (?, ?)", [$u['id'], $l['id']]);
            q("UPDATE listings SET favourites_count = favourites_count + 1 WHERE id = ?", [$l['id']]);
            q("INSERT INTO listing_stats_daily (listing_id, stat_date, favourites) VALUES (?, CURDATE(), 1) ON DUPLICATE KEY UPDATE favourites = favourites + 1", [$l['id']]);
        }
        json_ok(['favourited' => !$exists, 'count' => (int)val("SELECT COUNT(*) FROM favourites WHERE user_id = ?", [$u['id']])]);
    },
    'remove' => function () {
        $u = require_login();
        $l = find_listing((string)input('listing'));
        if ($l && q("DELETE FROM favourites WHERE user_id = ? AND listing_id = ?", [$u['id'], $l['id']])->rowCount()) {
            q("UPDATE listings SET favourites_count = GREATEST(favourites_count - 1, 0) WHERE id = ?", [$l['id']]);
        }
        json_ok([], 'Removed from favourites.');
    },
    'clear_inactive' => function () {
        $u = require_login();
        $n = q("DELETE f FROM favourites f JOIN listings l ON l.id = f.listing_id WHERE f.user_id = ? AND l.status <> 'active'", [$u['id']])->rowCount();
        json_ok(['removed' => $n], plural($n, 'closed ad') . ' removed.');
    },
]);
