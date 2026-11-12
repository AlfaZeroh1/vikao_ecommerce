<?php
/** Shows a simulated email from email_outbox (own emails only; staff can view all). */
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$m = one("SELECT * FROM email_outbox WHERE id = ?", [(int)($_GET['id'] ?? 0)]);
if (!$m || (!is_staff() && strtolower($m['to_email']) !== strtolower($me['email'])
    && !($m['related_type'] === 'order' && val("SELECT 1 FROM orders WHERE id = ? AND user_id = ?", [$m['related_id'], $me['id']])))) {
    http_response_code(404); exit('Email not found.');
}
header('Content-Type: text/html; charset=utf-8');
echo '<div style="font:13px Segoe UI,Arial;background:#0F172A;color:#E2E8F0;padding:10px 16px">Simulated email · To: <b>' . e($m['to_email']) . '</b> · Subject: <b>' . e($m['subject']) . '</b> · ' . e($m['created_at']) . '</div>';
echo $m['body_html'];
