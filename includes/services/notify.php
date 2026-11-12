<?php
/**
 * Notifications, transactional email (outbox) and PMS event outbox.
 */

function notify(int $userId, string $type, string $title, ?string $body = null, ?string $icon = null, ?string $link = null, array $data = []): void {
    insert('notifications', [
        'user_id' => $userId, 'type' => $type, 'title' => $title, 'body' => $body,
        'icon' => $icon, 'link_url' => $link, 'data' => $data ? json_encode($data) : null,
    ]);
}

function queue_email(string $to, ?string $toName, string $subject, string $bodyHtml, ?string $template = null, ?string $relType = null, ?int $relId = null): int {
    $driver = config('mail.driver', 'simulate');
    $id = insert('email_outbox', [
        'to_email' => $to, 'to_name' => $toName, 'subject' => $subject, 'body_html' => $bodyHtml,
        'template' => $template, 'status' => $driver === 'simulate' ? 'simulated' : 'queued',
        'related_type' => $relType, 'related_id' => $relId,
        'sent_at' => $driver === 'simulate' ? date('Y-m-d H:i:s') : null,
    ]);
    if ($driver === 'mail') {
        $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: " . config('mail.from_name') . ' <' . config('mail.from') . '>';
        $ok = @mail($to, $subject, $bodyHtml, $headers);
        update('email_outbox', ['status' => $ok ? 'sent' : 'failed', 'attempts' => 1, 'sent_at' => $ok ? date('Y-m-d H:i:s') : null], 'id = :id', ['id' => $id]);
    }
    return $id;
}

function pms_event(string $type, string $aggType, int $aggId, array $payload): int {
    return insert('pms_events', [
        'event_type' => $type, 'aggregate_type' => $aggType, 'aggregate_id' => $aggId,
        'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        // Simulated delivery: the mock PMS reads straight from this table.
        'status' => 'delivered', 'attempts' => 1, 'delivered_at' => date('Y-m-d H:i:s'),
    ]);
}

/** Branded email layout. */
function email_layout(string $title, string $inner): string {
    $app = e(config('app.name'));
    return <<<HTML
<!doctype html><html><body style="margin:0;background:#FDFBF7;font-family:Segoe UI,Arial,sans-serif;color:#1E293B">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#FDFBF7;padding:24px 0"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #eee7da">
<tr><td style="background:#1E293B;padding:20px 28px;color:#FDFBF7;font-size:22px;font-weight:700">{$app}<span style="color:#F59E0B">.</span></td></tr>
<tr><td style="padding:28px"><h1 style="font-size:20px;margin:0 0 16px">{$title}</h1>{$inner}</td></tr>
<tr><td style="padding:18px 28px;background:#F8F5EE;color:#64748B;font-size:12px">You received this email because of activity on your Vikao account · vikao.io</td></tr>
</table></td></tr></table></body></html>
HTML;
}

function email_order_summary(array $order): string {
    $snap = json_decode($order['listing_snapshot'], true) ?: [];
    $pkg  = $order['package_snapshot'] ? json_decode($order['package_snapshot'], true) : null;
    $rows = [
        ['Order number', $order['order_number']],
        ['Property', $snap['title'] ?? ''],
        ['Location', $snap['location'] ?? ''],
        ['Package', $pkg['name'] ?? 'Standard'],
        ['Package total', money($order['package_total'], $order['currency_code'])],
        ['Reservation fee paid', money($order['amount_paid'], $order['currency_code'])],
        ['Status', STATUS_META[$order['status']][0] ?? $order['status']],
    ];
    $tr = '';
    foreach ($rows as [$k, $v]) $tr .= '<tr><td style="padding:8px 0;color:#64748B">' . e($k) . '</td><td style="padding:8px 0;text-align:right;font-weight:600">' . e($v) . '</td></tr>';
    $link = e(rtrim(config('app.url'), '/') . '/order.php?id=' . $order['uuid']);
    return email_layout('Your reservation is confirmed ✅',
        '<p>Hi ' . e($order['contact_name']) . ', thank you for reserving with Vikao. Our team will reach out within 24 hours to arrange the next steps.</p>'
        . '<table width="100%" style="border-top:1px solid #eee;margin:16px 0">' . $tr . '</table>'
        . '<p><a href="' . $link . '" style="display:inline-block;background:#D97706;color:#fff;padding:12px 20px;border-radius:10px;text-decoration:none;font-weight:600">View your order</a></p>');
}
