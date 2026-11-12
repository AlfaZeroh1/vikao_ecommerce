<?php
/**
 * Shared bootstrap for AJAX controllers: JSON errors, CSRF on writes, action routing.
 */
require __DIR__ . '/../includes/bootstrap.php';

set_exception_handler(function (Throwable $e) {
    if ($e instanceof ValidationError) json_fail($e->getMessage(), 422, $e->errors);
    if ($e instanceof RuntimeException && !($e instanceof PDOException)) json_fail($e->getMessage(), 400);
    error_log('[vikao] ' . $e);
    json_fail(config('app.env') === 'production' ? 'Server error. Please try again.' : $e->getMessage(), 500);
});

function action(): string { return (string)($_POST['action'] ?? $_GET['action'] ?? ''); }

// CSRF on every write, except the public M-Pesa (Daraja) server-to-server callback
$isGatewayCallback = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'payments.php' && action() === 'callback';
if (is_post() && !$isGatewayCallback) verify_csrf();

/** Dispatch to closure by action name. */
function route(array $map): never {
    $a = action();
    if (!isset($map[$a])) json_fail('Unknown action.', 404);
    $map[$a]();
    json_fail('No response.', 500);
}
