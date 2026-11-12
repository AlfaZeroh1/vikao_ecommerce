<?php
require __DIR__ . '/_init.php';

function msg_payload(array $m, int $me): array {
    return ['id' => (int)$m['id'], 'mine' => (int)$m['sender_id'] === $me, 'html' => linkify($m['body']),
            'time' => date('H:i', strtotime($m['created_at'])), 'date' => date('Y-m-d', strtotime($m['created_at'])), 'read' => (bool)$m['read_at'], 'type' => $m['message_type']];
}

route([
    // From ad page: open (or reuse) the conversation for this ad, optionally sending a first message
    'start' => function () {
        $u = require_login();
        $l = find_listing((string)input('listing'));
        if (!$l) json_fail('Listing not found.', 404);
        $conv = start_conversation($l, $u);
        $first = trim((string)input('message', ''));
        if ($first !== '') send_message($conv, (int)$u['id'], $first);
        json_ok(['conversation' => $conv['uuid'], 'redirect' => url('chats.php', ['c' => $conv['uuid']])]);
    },

    'list' => function () {
        $u = require_login();
        $rows = user_conversations((int)$u['id'], (string)input('q', ''));
        $filter = (string)input('filter', 'all');
        $out = [];
        foreach ($rows as $c) {
            if ($filter === 'unread' && !$c['my_unread']) continue;
            if (in_array($filter, ['buying', 'selling'], true) && $c['my_role'] !== $filter) continue;
            $out[] = [
                'id' => $c['uuid'], 'listing_title' => $c['listing_title'], 'listing_uuid' => $c['listing_uuid'],
                'thumb' => media_url($c['listing_thumb']), 'other' => $c['other_name'], 'initials' => initials($c['other_name']),
                'last' => $c['last_body'] ? excerpt(($c['last_sender'] == $u['id'] ? 'You: ' : '') . $c['last_body'], 70) : 'No messages yet',
                'when' => $c['last_message_at'] ? time_ago($c['last_message_at']) : '', 'unread' => (int)$c['my_unread'], 'role' => $c['my_role'],
                'price' => money($c['price'], $c['currency_code'], true), 'listing_status' => $c['listing_status'],
                'online' => $c['other_seen'] && strtotime($c['other_seen']) > time() - 300,
            ];
        }
        json_ok(['items' => $out]);
    },

    'messages' => function () {
        $u = require_login();
        $conv = conversation_for_user((string)input('c'), (int)$u['id']);
        if (!$conv) json_fail('Conversation not found.', 404);
        $after = input_int('after', 0);
        $rows = $after
            ? all("SELECT * FROM messages WHERE conversation_id = ? AND id > ? AND deleted_at IS NULL ORDER BY id LIMIT 200", [$conv['id'], $after])
            : array_reverse(all("SELECT * FROM messages WHERE conversation_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 200", [$conv['id']]));
        mark_conversation_read($conv, (int)$u['id']);
        $otherId = (int)$conv['buyer_id'] === (int)$u['id'] ? (int)$conv['seller_id'] : (int)$conv['buyer_id'];
        $other = one("SELECT last_seen_at FROM users WHERE id = ?", [$otherId]);
        json_ok([
            'items' => array_map(fn($m) => msg_payload($m, (int)$u['id']), $rows),
            'other_online' => $other['last_seen_at'] && strtotime($other['last_seen_at']) > time() - 300,
            'other_seen' => $other['last_seen_at'] ? time_ago($other['last_seen_at']) : null,
            'read_upto' => (int)val("SELECT COALESCE(MAX(id),0) FROM messages WHERE conversation_id = ? AND sender_id = ? AND read_at IS NOT NULL", [$conv['id'], $u['id']]),
        ]);
    },

    'send' => function () {
        $u = require_login();
        if (!rate_limit('chat:' . $u['id'], 40, 60)) json_fail('Slow down a little 🙂', 429);
        $conv = conversation_for_user((string)input('c'), (int)$u['id']);
        if (!$conv) json_fail('Conversation not found.', 404);
        $m = send_message($conv, (int)$u['id'], (string)input('body', ''));
        json_ok(['message' => msg_payload($m, (int)$u['id'])]);
    },
]);
