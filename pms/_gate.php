<?php
/**
 * Mock PMS access gate. Real PMS will be its own app sharing identity; for the MVP:
 *  - staff/admin users (role) get in directly, or
 *  - any signed-in user who enters the PMS passcode (config pms.passcode).
 */
if (!defined('APP_ROOT')) require __DIR__ . '/../includes/bootstrap.php';

function pms_allowed(): bool { return is_staff() || !empty($_SESSION['pms_ok']); }

function pms_require(): array {
    $u = require_login();
    if (pms_allowed()) return $u;
    if (is_post() && isset($_POST['passcode'])) {
        verify_csrf();
        if (hash_equals((string)config('pms.passcode'), (string)$_POST['passcode'])) { $_SESSION['pms_ok'] = 1; redirect($_SERVER['REQUEST_URI']); }
        flash('error', 'Wrong passcode.');
    }
    $pageTitle = 'Vikao PMS';
    include APP_ROOT . '/includes/layout/header.php'; ?>
    <div class="mx-auto max-w-md px-4 py-16">
      <form method="post" class="vk-panel p-8 text-center shadow-soft">
        <?= csrf_field() ?>
        <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-nav text-amber-400 text-2xl"><i class="fa-solid fa-building-shield"></i></span>
        <h1 class="mt-4 font-display text-2xl font-bold">Vikao PMS</h1>
        <p class="text-muted text-sm">Staff area. Sign in with a staff account or enter the PMS passcode.</p>
        <input name="passcode" type="password" class="vk-input mt-6 text-center" placeholder="PMS passcode" autofocus>
        <button class="vk-btn-accent w-full mt-3"><i class="fa-solid fa-unlock"></i>Enter PMS</button>
      </form>
    </div>
    <?php include APP_ROOT . '/includes/layout/footer.php';
    exit;
}
