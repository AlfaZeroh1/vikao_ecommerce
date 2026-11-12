<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$orders = all("SELECT o.*, (SELECT provider_receipt FROM payments p WHERE p.order_id = o.id AND p.status = 'success' LIMIT 1) AS receipt FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC", [$me['id']]);
$paid = array_sum(array_map(fn($o) => (float)$o['amount_paid'], $orders));

$pageTitle = 'My orders';
include APP_ROOT . '/includes/layout/header.php';
component('page_header', ['icon' => 'fa-solid fa-bag-shopping', 'title' => 'My orders', 'subtitle' => 'Reservations you\'ve made on the Official Vikao Store' . ($paid ? ' · <b class="text-ink">' . e(money($paid)) . '</b> paid' : ''),
    'actions' => '<a href="' . url('shop.php', ['official' => 1]) . '" class="vk-btn-ghost"><i class="fa-solid fa-crown text-amber-500"></i>Official Store</a>']);
?>
<div class="mx-auto max-w-5xl px-4 sm:px-6 space-y-3">
  <?php if (!$orders): component('empty_state', ['icon' => 'fa-bag-shopping', 'title' => 'No orders yet', 'text' => 'Reserve verified properties from the Official Vikao Store with M-Pesa.', 'cta' => 'Browse Official Store', 'href' => url('shop.php', ['official' => 1])]); endif; ?>
  <?php foreach ($orders as $o): $s = json_decode($o['listing_snapshot'], true); $p = $o['package_snapshot'] ? json_decode($o['package_snapshot'], true) : null; ?>
    <a href="<?= url($o['status'] === 'pending_payment' ? 'checkout.php' : 'order.php', $o['status'] === 'pending_payment' ? ['order' => $o['uuid']] : ['id' => $o['uuid']]) ?>" class="vk-panel p-4 flex flex-col sm:flex-row gap-4 hover:border-accent/50 hover:shadow-soft transition">
      <img src="<?= e(media_url($s['cover'] ?? null)) ?>" alt="" class="h-24 w-full sm:w-32 rounded-2xl object-cover shrink-0">
      <div class="flex-1 min-w-0">
        <div class="flex flex-wrap items-center gap-2"><?= status_pill($o['status']) ?><span class="font-mono text-xs font-bold text-muted"><?= e($o['order_number']) ?></span></div>
        <p class="mt-1.5 font-bold leading-snug line-clamp-1"><?= e($s['title'] ?? '') ?></p>
        <p class="text-xs text-muted truncate"><i class="fa-solid fa-location-dot"></i> <?= e($s['location'] ?? '') ?><?= $p ? ' · <i class="fa-solid fa-box-open"></i> ' . e($p['name']) : '' ?></p>
        <p class="mt-1 text-xs text-muted"><i class="fa-regular fa-calendar"></i> <?= e(fdate($o['created_at'], 'j M Y, g:i a')) ?><?= $o['receipt'] ? ' · <i class="fa-solid fa-receipt"></i> ' . e($o['receipt']) : '' ?></p>
      </div>
      <div class="sm:text-right shrink-0 flex sm:flex-col justify-between sm:justify-center gap-1">
        <p class="text-xs text-muted"><?= $o['status'] === 'pending_payment' ? 'Due' : 'Paid' ?></p>
        <p class="font-display text-xl font-bold <?= $o['status'] === 'pending_payment' ? 'text-amber-600' : 'text-accent' ?>"><?= e(money($o['status'] === 'pending_payment' ? $o['amount_due'] : $o['amount_paid'], $o['currency_code'])) ?></p>
        <span class="text-xs font-bold text-accent"><?= $o['status'] === 'pending_payment' ? 'Complete payment' : 'View details' ?> <i class="fa-solid fa-arrow-right"></i></span>
      </div>
    </a>
  <?php endforeach; ?>
</div>
<?php include APP_ROOT . '/includes/layout/footer.php'; ?>
