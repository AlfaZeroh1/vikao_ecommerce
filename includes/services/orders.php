<?php
/**
 * Official-listing reservation pipeline:
 *   Reserve now -> order page (package + contact) -> create_order() -> checkout
 *   -> start_payment() (M-Pesa STK, simulated) -> complete_payment() -> order paid
 *   -> email summary + notification + PMS event.
 */

function generate_order_number(): string {
    do { $n = 'VKO-' . date('ymd') . '-' . random_int(10000, 99999); } while (val("SELECT 1 FROM orders WHERE order_number = ?", [$n]));
    return $n;
}

function order_status_change(int $orderId, ?string $from, string $to, string $actor, ?string $note = null): void {
    insert('order_status_history', ['order_id' => $orderId, 'from_status' => $from, 'to_status' => $to, 'actor' => $actor, 'note' => $note]);
}

function create_order(array $listing, ?array $package, array $user, array $contact): array {
    if (!$listing['is_official'] || $listing['status'] !== 'active') throw new RuntimeException('This listing cannot be reserved.');
    $fee = (float)$listing['reservation_fee'];
    if ($fee <= 0) throw new RuntimeException('This listing has no reservation fee configured.');
    $chain = location_chain((int)$listing['location_id']);
    $snapshot = [
        'id' => (int)$listing['id'], 'uuid' => $listing['uuid'], 'ref_code' => $listing['ref_code'], 'title' => $listing['title'],
        'price' => $listing['price'], 'price_suffix' => price_suffix($listing), 'currency' => $listing['currency_code'],
        'location' => implode(', ', array_reverse(array_column($chain, 'name'))), 'family' => $listing['family'], 'deal_type' => $listing['deal_type'],
        'cover' => val("SELECT thumb_path FROM listing_media WHERE id = ?", [$listing['cover_media_id']]),
    ];
    return tx(function () use ($listing, $package, $user, $contact, $fee, $snapshot) {
        $id = insert('orders', [
            'uuid' => uuid4(), 'order_number' => generate_order_number(), 'user_id' => (int)$user['id'],
            'listing_id' => (int)$listing['id'], 'package_id' => $package ? (int)$package['id'] : null,
            'contact_name' => $contact['name'], 'contact_email' => $contact['email'], 'contact_phone' => $contact['phone'],
            'id_number' => $contact['id_number'] ?: null, 'preferred_contact' => $contact['preferred_contact'],
            'preferred_viewing_date' => $contact['viewing_date'] ?: null, 'notes' => $contact['notes'] ?: null,
            'listing_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'package_snapshot' => $package ? json_encode($package, JSON_UNESCAPED_UNICODE) : null,
            'package_total' => $package ? (float)$package['total_price'] : (float)$listing['price'],
            'reservation_fee' => $fee, 'amount_due' => $fee, 'currency_code' => $listing['currency_code'],
        ]);
        order_status_change($id, null, 'pending_payment', 'customer', 'Order created');
        log_activity((int)$user['id'], 'order.create', 'order', $id);
        return one("SELECT * FROM orders WHERE id = ?", [$id]);
    });
}

/** Initiates STK push. Simulated: we create a pending payment the UI completes. */
function start_payment(array $order, string $methodCode, string $phone): array {
    $method = one("SELECT * FROM payment_methods WHERE code = ? AND is_active = 1", [$methodCode]);
    if (!$method) throw new RuntimeException('That payment method is not available yet.');
    if ($order['status'] !== 'pending_payment') throw new RuntimeException('This order is already ' . str_replace('_', ' ', $order['status']) . '.');
    $simulate = (bool)config('payments.mpesa.simulate', true);
    // cancel stale pending attempts
    q("UPDATE payments SET status = 'cancelled', completed_at = NOW() WHERE order_id = ? AND status IN ('initiated','pending')", [$order['id']]);
    $merchant = 'MR-' . strtoupper(bin2hex(random_bytes(5)));
    $checkout = 'ws_CO_' . date('dmYHis') . random_int(100000, 999999);
    $payload = [
        'BusinessShortCode' => config('payments.mpesa.shortcode'), 'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => (int)ceil((float)$order['amount_due']), 'PartyA' => ltrim($phone, '+'), 'PhoneNumber' => ltrim($phone, '+'),
        'AccountReference' => $order['order_number'], 'TransactionDesc' => 'Vikao reservation',
    ];
    // Real integration point: POST $payload to Daraja /mpesa/stkpush/v1/processrequest here.
    $id = insert('payments', [
        'uuid' => uuid4(), 'order_id' => (int)$order['id'], 'payment_method_id' => (int)$method['id'],
        'amount' => $order['amount_due'], 'currency_code' => $order['currency_code'], 'payer_phone' => $phone,
        'merchant_request_id' => $merchant, 'checkout_request_id' => $checkout, 'status' => 'pending',
        'is_simulated' => $simulate ? 1 : 0, 'request_payload' => json_encode($payload),
    ]);
    return one("SELECT * FROM payments WHERE id = ?", [$id]);
}

/** Handles the (simulated) Daraja callback. $success false => failed/cancelled. */
function complete_payment(array $payment, bool $success, string $resultCode = '0', string $resultDesc = 'The service request is processed successfully.'): array {
    return tx(function () use ($payment, $success, $resultCode, $resultDesc) {
        $p = one("SELECT * FROM payments WHERE id = ? FOR UPDATE", [$payment['id']]);
        if ($p['status'] !== 'pending') return $p;
        $order = one("SELECT * FROM orders WHERE id = ? FOR UPDATE", [$p['order_id']]);
        if (!$success) {
            update('payments', ['status' => $resultCode === '1032' ? 'cancelled' : 'failed', 'result_code' => $resultCode, 'result_desc' => $resultDesc, 'completed_at' => date('Y-m-d H:i:s'),
                'callback_payload' => json_encode(['ResultCode' => (int)$resultCode, 'ResultDesc' => $resultDesc])], 'id = :id', ['id' => $p['id']]);
            return one("SELECT * FROM payments WHERE id = ?", [$p['id']]);
        }
        $receipt = 'S' . strtoupper(random_code(9, 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789'));
        update('payments', [
            'status' => 'success', 'result_code' => '0', 'result_desc' => $resultDesc, 'provider_receipt' => $receipt, 'completed_at' => date('Y-m-d H:i:s'),
            'callback_payload' => json_encode(['ResultCode' => 0, 'ResultDesc' => $resultDesc, 'CallbackMetadata' => ['Amount' => (float)$p['amount'], 'MpesaReceiptNumber' => $receipt, 'PhoneNumber' => $p['payer_phone'], 'TransactionDate' => date('YmdHis')]]),
        ], 'id = :id', ['id' => $p['id']]);
        update('orders', ['status' => 'paid', 'amount_paid' => $p['amount'], 'paid_at' => date('Y-m-d H:i:s'), 'pms_sync_status' => 'sent'], 'id = :id', ['id' => $order['id']]);
        order_status_change((int)$order['id'], $order['status'], 'paid', 'payment', "M-Pesa receipt $receipt");
        $order = one("SELECT * FROM orders WHERE id = ?", [$order['id']]);

        // 1) customer notification
        notify((int)$order['user_id'], 'order_paid', 'Reservation confirmed · ' . $order['order_number'], 'We received ' . money($p['amount'], $order['currency_code']) . '. Our team will contact you within 24 hours.', 'fa-receipt', url('order.php', ['id' => $order['uuid']]));
        // 2) email summary
        queue_email($order['contact_email'], $order['contact_name'], 'Order ' . $order['order_number'] . ' confirmed — Vikao', email_order_summary($order), 'order_paid', 'order', (int)$order['id']);
        // 3) alert the PMS
        $snap = json_decode($order['listing_snapshot'], true);
        pms_event('order.paid', 'order', (int)$order['id'], [
            'order_number' => $order['order_number'], 'listing' => $snap, 'package' => $order['package_snapshot'] ? json_decode($order['package_snapshot'], true) : null,
            'amount_paid' => (float)$order['amount_paid'], 'currency' => $order['currency_code'], 'mpesa_receipt' => $receipt,
            'customer' => ['name' => $order['contact_name'], 'email' => $order['contact_email'], 'phone' => $order['contact_phone'], 'preferred_contact' => $order['preferred_contact'], 'viewing_date' => $order['preferred_viewing_date']],
        ]);
        // 4) notify official store owners
        if ($off = official_user_id()) notify($off, 'order_received', 'New reservation ' . $order['order_number'], $order['contact_name'] . ' reserved "' . ($snap['title'] ?? '') . '"', 'fa-bag-shopping', url('pms/index.php'));
        return one("SELECT * FROM payments WHERE id = ?", [$p['id']]);
    });
}
