<?php
require __DIR__ . '/_init.php';

route([
    'counts' => function () {
        $u = current_user();
        if (!$u) json_ok(['notifications' => 0, 'chats' => 0]);
        json_ok([
            'notifications' => (int)val("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL", [$u['id']]),
            'chats' => unread_chat_count((int)$u['id']),
        ]);
    },
    'read' => function () {
        $u = require_login();
        q("UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL", [input_int('id', 0), $u['id']]);
        json_ok();
    },
    'read_all' => function () {
        $u = require_login();
        $n = q("UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL", [$u['id']])->rowCount();
        json_ok(['updated' => $n], 'All caught up ✨');
    },
    'delete' => function () {
        $u = require_login();
        q("DELETE FROM notifications WHERE id = ? AND user_id = ?", [input_int('id', 0), $u['id']]);
        json_ok([], 'Notification removed.');
    },
]);
