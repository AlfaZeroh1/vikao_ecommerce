<?php
require __DIR__ . '/_init.php';

function own_order(array $u): array {
    $o = one("SELECT * FROM orders WHERE uuid = ? AND user_id = ?", [(string)input('order'), $u['id']]);
    if (!$o) json_fail('Order not found.', 404);
    return $o;
}

route([
    // Order page submit: package + contact details -> generates order number, go to checkout
    'create' => function () {
        $u = require_login();
        $l = find_listing((string)input('listing'));
        if (!$l || !$l['is_official'] || $l['status'] !== 'active') json_fail('This property is no longer available for reservation.', 404);
        $pkgs = all("SELECT * FROM listing_packages WHERE listing_id = ? AND is_active = 1", [$l['id']]);
        $pkg = null;
        if ($pkgs) {
            $pid = input_int('package_id', 0);
            foreach ($pkgs as $p) if ((int)$p['id'] === $pid) $pkg = $p;
        }
        $contact = [
            'name' => trim((string)input('contact_name', '')), 'email' => strtolower(trim((string)input('contact_email', ''))),
            'phone' => normalize_phone((string)input('contact_phone', '')), 'id_number' => mb_substr(trim((string)input('id_number', '')), 0, 30),
            'preferred_contact' => in_array(input('preferred_contact'), ['phone', 'email', 'whatsapp'], true) ? input('preferred_contact') : 'phone',
            'viewing_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)input('viewing_date')) ? input('viewing_date') : null,
            'notes' => mb_substr(trim((string)input('notes', '')), 0, 1000),
        ];
        $err = [];
        if ($pkgs && !$pkg) $err['package_id'] = 'Choose a package.';
        if (mb_strlen($contact['name']) < 3) $err['contact_name'] = 'Enter your full name.';
        if (!filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) $err['contact_email'] = 'Enter a valid email.';
        if (!$contact['phone']) $err['contact_phone'] = 'Enter a valid phone number.';
        if ($contact['viewing_date'] && $contact['viewing_date'] < date('Y-m-d')) $err['viewing_date'] = 'Pick a future date.';
        if (empty($_POST['agree'])) $err['agree'] = 'Please accept the reservation terms.';
        if ($err) json_fail('Please fix the highlighted fields.', 422, $err);
        // reuse an unpaid order for the same listing+package to avoid duplicates
        $open = one("SELECT * FROM orders WHERE user_id = ? AND listing_id = ? AND status = 'pending_payment' AND created_at > (NOW() - INTERVAL 1 DAY) ORDER BY id DESC LIMIT 1", [$u['id'], $l['id']]);
        if ($open) {
            update('orders', ['package_id' => $pkg ? (int)$pkg['id'] : null, 'package_snapshot' => $pkg ? json_encode($pkg) : null,
                'package_total' => $pkg ? (float)$pkg['total_price'] : (float)$l['price'], 'contact_name' => $contact['name'], 'contact_email' => $contact['email'],
                'contact_phone' => $contact['phone'], 'id_number' => $contact['id_number'] ?: null, 'preferred_contact' => $contact['preferred_contact'],
                'preferred_viewing_date' => $contact['viewing_date'], 'notes' => $contact['notes'] ?: null], 'id = :id', ['id' => $open['id']]);
            $order = one("SELECT * FROM orders WHERE id = ?", [$open['id']]);
        } else {
            $order = create_order($l, $pkg, $u, $contact);
        }
        json_ok(['order' => $order['uuid'], 'order_number' => $order['order_number'], 'redirect' => url('checkout.php', ['order' => $order['uuid']])], 'Order ' . $order['order_number'] . ' created.');
    },

    'cancel' => function () {
        $u = require_login(); $o = own_order($u);
        if ($o['status'] !== 'pending_payment') json_fail('Only unpaid orders can be cancelled here. Contact support for paid orders.');
        q("UPDATE orders SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?", [$o['id']]);
        order_status_change((int)$o['id'], $o['status'], 'cancelled', 'customer', 'Cancelled by customer');
        json_ok(['redirect' => url('orders.php')], 'Order cancelled.');
    },
]);
