<?php
/**
 * Ad-scoped chat. One conversation per (listing, buyer). Chats only start from an ad page.
 */

function start_conversation(array $listing, array $buyer): array {
    if ((int)$listing['user_id'] === (int)$buyer['id']) throw new RuntimeException("You can't chat with yourself about your own ad.");
    if ($listing['is_official']) throw new RuntimeException('Official Vikao listings are reserved online — use "Reserve now".');
    if ($listing['status'] !== 'active') throw new RuntimeException('This ad is no longer active.');
    $c = one("SELECT * FROM conversations WHERE listing_id = ? AND buyer_id = ?", [$listing['id'], $buyer['id']]);
    if ($c) return $c;
    $id = insert('conversations', ['uuid' => uuid4(), 'listing_id' => (int)$listing['id'], 'buyer_id' => (int)$buyer['id'], 'seller_id' => (int)$listing['user_id']]);
    q("UPDATE listings SET chats_count = chats_count + 1 WHERE id = ?", [$listing['id']]);
    q("INSERT INTO listing_stats_daily (listing_id, stat_date, chats_started) VALUES (?, CURDATE(), 1) ON DUPLICATE KEY UPDATE chats_started = chats_started + 1", [$listing['id']]);
    return one("SELECT * FROM conversations WHERE id = ?", [$id]);
}

function conversation_for_user(string $uuid, int $userId): ?array {
    return one("SELECT * FROM conversations WHERE uuid = ? AND (buyer_id = ? OR seller_id = ?)", [$uuid, $userId, $userId]);
}

function send_message(array $conv, int $senderId, string $body): array {
    $body = trim(mb_substr(preg_replace('/[^\P{C}\n\t]/u', '', $body), 0, 2000));
    if ($body === '') throw new RuntimeException('Message is empty.');
    if ($conv['blocked_by']) throw new RuntimeException('This conversation is blocked.');
    $isBuyer = (int)$conv['buyer_id'] === $senderId;
    $id = tx(function () use ($conv, $senderId, $body, $isBuyer) {
        $id = insert('messages', ['conversation_id' => (int)$conv['id'], 'sender_id' => $senderId, 'body' => $body]);
        $col = $isBuyer ? 'seller_unread' : 'buyer_unread';
        q("UPDATE conversations SET last_message_id = ?, last_message_at = NOW(), $col = $col + 1, buyer_archived_at = NULL, seller_archived_at = NULL WHERE id = ?", [$id, $conv['id']]);
        return $id;
    });
    // notify recipient at most once per 10 minutes per conversation
    $recipient = $isBuyer ? (int)$conv['seller_id'] : (int)$conv['buyer_id'];
    $recent = val("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'new_message' AND link_url LIKE ? AND created_at > (NOW() - INTERVAL 10 MINUTE)", [$recipient, '%' . $conv['uuid']]);
    if (!$recent) {
        $sender = one("SELECT display_name FROM users WHERE id = ?", [$senderId]);
        $title = val("SELECT title FROM listings WHERE id = ?", [$conv['listing_id']]);
        notify($recipient, 'new_message', 'New message from ' . $sender['display_name'], excerpt($title, 60) . ' — "' . excerpt($body, 80) . '"', 'fa-comment-dots', url('chats.php', ['c' => $conv['uuid']]));
    }
    return one("SELECT * FROM messages WHERE id = ?", [$id]);
}

function mark_conversation_read(array $conv, int $userId): void {
    $isBuyer = (int)$conv['buyer_id'] === $userId;
    q("UPDATE conversations SET " . ($isBuyer ? 'buyer_unread' : 'seller_unread') . " = 0 WHERE id = ?", [$conv['id']]);
    q("UPDATE messages SET read_at = NOW() WHERE conversation_id = ? AND sender_id <> ? AND read_at IS NULL", [$conv['id'], $userId]);
}

function user_conversations(int $userId, string $search = ''): array {
    $p = ['u1' => $userId, 'u2' => $userId, 'u3' => $userId, 'u4' => $userId];
    $extra = '';
    if ($search !== '') { $extra = ' AND (l.title LIKE :s OR o.display_name LIKE :s)'; $p['s'] = "%$search%"; }
    return all("SELECT c.*, l.uuid AS listing_uuid, l.title AS listing_title, l.price, l.currency_code, l.price_unit, l.billing_period, l.status AS listing_status,
            m.thumb_path AS listing_thumb, o.id AS other_id, o.display_name AS other_name, o.avatar_url AS other_avatar, o.last_seen_at AS other_seen,
            lm.body AS last_body, lm.sender_id AS last_sender,
            CASE WHEN c.buyer_id = :u1 THEN c.buyer_unread ELSE c.seller_unread END AS my_unread,
            CASE WHEN c.buyer_id = :u2 THEN 'buying' ELSE 'selling' END AS my_role
        FROM conversations c
        JOIN listings l ON l.id = c.listing_id
        LEFT JOIN listing_media m ON m.id = l.cover_media_id
        JOIN users o ON o.id = CASE WHEN c.buyer_id = :u3 THEN c.seller_id ELSE c.buyer_id END
        LEFT JOIN messages lm ON lm.id = c.last_message_id
        WHERE (c.buyer_id = :u4 OR c.seller_id = :u4)$extra
        ORDER BY COALESCE(c.last_message_at, c.created_at) DESC LIMIT 200", $p);
}

function unread_chat_count(int $userId): int {
    return (int)val("SELECT COALESCE(SUM(CASE WHEN buyer_id = ? THEN buyer_unread ELSE seller_unread END), 0) FROM conversations WHERE buyer_id = ? OR seller_id = ?", [$userId, $userId, $userId]);
}
