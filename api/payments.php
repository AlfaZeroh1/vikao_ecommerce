<?php
require __DIR__ . '/_init.php';

function own_payment(array $u): array {
    $p = one("SELECT p.* FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.uuid = ? AND o.user_id = ?", [(string)input('payment'), $u['id']]);
    if (!$p) json_fail('Payment not found.', 404);
    return $p;
}

route([
    // STK push (simulated): creates pending payment, UI shows phone prompt
    'stk_push' => function () {
        $u = require_login();
        $order = one("SELECT * FROM orders WHERE uuid = ? AND user_id = ?", [(string)input('order'), $u['id']]);
        if (!$order) json_fail('Order not found.', 404);
        $phone = normalize_phone((string)input('phone', ''));
        if (!$phone || !preg_match('/^\+254(7|1)\d{8}$/', $phone)) json_fail('Enter a valid Safaricom number, e.g. 0712 345 678.', 422, ['phone' => 'Enter a valid Safaricom number.']);
        if (!rate_limit('stk:' . $u['id'], 8, 300)) json_fail('Too many payment attempts. Wait a few minutes.', 429);
        $p = start_payment($order, (string)input('method', 'mpesa'), $phone);
        json_ok([
            'payment' => $p['uuid'], 'checkout_request_id' => $p['checkout_request_id'], 'simulated' => (bool)$p['is_simulated'],
            'amount' => (float)$p['amount'], 'phone' => preg_replace('/^\+254/', '0', $phone),
            'CustomerMessage' => 'Success. Request accepted for processing',
        ], 'STK push sent to ' . preg_replace('/^\+254/', '0', $phone));
    },

    // Simulator only: the "phone" posts the PIN result here (acts like Daraja's callback)
    'simulate' => function () {
        if (!config('payments.mpesa.simulate')) json_fail('Simulation disabled.', 403);
        $u = require_login(); $p = own_payment($u);
        $result = (string)input('result', 'success');
        $map = ['success' => [true, '0', 'The service request is processed successfully.'],
                'cancel'  => [false, '1032', 'Request cancelled by user.'],
                'insufficient' => [false, '1', 'The balance is insufficient for the transaction.'],
                'wrong_pin' => [false, '2001', 'The initiator information is invalid.']];
        [$ok, $code, $desc] = $map[$result] ?? $map['success'];
        if ($result === 'success' && !preg_match('/^\d{4}$/', (string)input('pin', ''))) json_fail('Enter your 4-digit M-Pesa PIN.');
        usleep(600000); // feel of network latency
        $p = complete_payment($p, $ok, $code, $desc);
        json_ok(['status' => $p['status']]);
    },

    'status' => function () {
        $u = require_login(); $p = own_payment($u);
        $o = one("SELECT uuid, order_number, status FROM orders WHERE id = ?", [$p['order_id']]);
        json_ok(['status' => $p['status'], 'result_desc' => $p['result_desc'], 'receipt' => $p['provider_receipt'],
                 'order_status' => $o['status'], 'redirect' => $p['status'] === 'success' ? url('order.php', ['id' => $o['uuid'], 'placed' => 1]) : null]);
    },

    // Real Daraja callback endpoint (public). Wire when simulate = false.
    'callback' => function () {
        $body = json_decode(file_get_contents('php://input'), true);
        $cb = $body['Body']['stkCallback'] ?? null;
        if (!$cb) json_out(['ResultCode' => 1, 'ResultDesc' => 'Invalid payload'], 400);
        $p = one("SELECT * FROM payments WHERE checkout_request_id = ?", [$cb['CheckoutRequestID'] ?? '']);
        if ($p) complete_payment($p, (int)$cb['ResultCode'] === 0, (string)$cb['ResultCode'], (string)$cb['ResultDesc']);
        json_out(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    },
]);
