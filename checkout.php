<?php
/** Checkout (step 2): order number exists; choose payment method. */
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$order = one("SELECT * FROM orders WHERE uuid = ? AND user_id = ?", [(string)($_GET['order'] ?? ''), $me['id']]);
if (!$order) { flash('error', 'Order not found.'); redirect('orders.php'); }
if ($order['status'] !== 'pending_payment') redirect('order.php?id=' . $order['uuid']);
$snap = json_decode($order['listing_snapshot'], true);
$pkg = $order['package_snapshot'] ? json_decode($order['package_snapshot'], true) : null;
$methods = all("SELECT * FROM payment_methods ORDER BY sort_order");
$logos = [
    'mpesa' => '<span class="grid h-12 w-12 place-items-center rounded-xl bg-[#00A651] text-white font-black text-[11px] leading-none text-center">M<br>PESA</span>',
    'airtel' => '<span class="grid h-12 w-12 place-items-center rounded-xl bg-[#E4002B] text-white font-black text-[10px] leading-none text-center">airtel<br>money</span>',
    'card' => '<span class="grid h-12 w-12 place-items-center rounded-xl bg-slate-800 text-white text-xl"><i class="fa-brands fa-cc-visa"></i></span>',
    'bank' => '<span class="grid h-12 w-12 place-items-center rounded-xl bg-sky-700 text-white text-lg"><i class="fa-solid fa-building-columns"></i></span>',
];
$pageTitle = 'Checkout · ' . $order['order_number'];
include APP_ROOT . '/includes/layout/header.php';
?>
<div class="mx-auto max-w-6xl px-4 sm:px-6 pt-8">
  <?php component('checkout_steps', ['step' => 2]); ?>
  <div class="flex flex-wrap items-end gap-3 mb-6">
    <div><h1 class="font-display text-2xl sm:text-3xl font-bold">Checkout</h1><p class="text-muted">Choose how you'd like to pay your reservation fee.</p></div>
    <div class="ml-auto rounded-2xl bg-surface border border-line px-4 py-2.5 text-sm">
      <span class="text-muted">Order no.</span> <b class="font-mono tracking-wide"><?= e($order['order_number']) ?></b>
      <button type="button" class="ml-1 text-muted hover:text-accent" data-copy="<?= e($order['order_number']) ?>" aria-label="Copy"><i class="fa-regular fa-copy"></i></button>
    </div>
  </div>
  <div class="grid lg:grid-cols-[1fr_380px] gap-6 items-start">
    <div class="space-y-6">
      <section class="vk-panel p-5 sm:p-7">
        <h2 class="vk-section-title mb-4"><i class="fa-solid fa-wallet"></i>Payment method</h2>
        <div class="space-y-3">
          <?php foreach ($methods as $m): $on = (bool)$m['is_active']; ?>
            <label class="relative flex items-center gap-4 rounded-2xl border-2 p-4 transition <?= $on ? 'cursor-pointer border-line hover:border-accent/50 has-[:checked]:border-accent has-[:checked]:bg-accent/5' : 'border-line opacity-55 cursor-not-allowed' ?>">
              <input type="radio" name="method" value="<?= e($m['code']) ?>" class="sr-only" <?= $on ? '' : 'disabled' ?> <?= $m['code'] === 'mpesa' ? 'checked' : '' ?>>
              <?= $logos[$m['icon']] ?? '' ?>
              <span class="flex-1"><span class="block font-bold"><?= e($m['name']) ?></span>
                <span class="block text-xs text-muted"><?= $m['code'] === 'mpesa' ? 'Pay instantly with an STK push to your phone' : 'Coming soon' ?></span></span>
              <?php if ($on): ?><span class="vk-badge bg-emerald-500/15 text-emerald-600"><i class="fa-solid fa-bolt"></i>Instant</span><?php else: ?><span class="vk-badge bg-surface2 text-muted">Soon</span><?php endif; ?>
            </label>
          <?php endforeach; ?>
        </div>
      </section>
      <section class="vk-panel p-5 sm:p-7">
        <h2 class="vk-section-title mb-3"><i class="fa-solid fa-address-card"></i>Contact for this order</h2>
        <div class="grid sm:grid-cols-3 gap-3 text-sm">
          <div><p class="text-muted text-xs">Name</p><p class="font-semibold"><?= e($order['contact_name']) ?></p></div>
          <div><p class="text-muted text-xs">Email</p><p class="font-semibold break-all"><?= e($order['contact_email']) ?></p></div>
          <div><p class="text-muted text-xs">Phone</p><p class="font-semibold"><?= e($order['contact_phone']) ?></p></div>
        </div>
        <a href="<?= url('reserve.php', ['id' => $snap['uuid']]) ?>" class="mt-3 inline-block text-sm font-bold text-accent"><i class="fa-solid fa-pen"></i> Edit details</a>
      </section>
    </div>
    <aside class="lg:sticky lg:top-32 space-y-4">
      <?php component('order_summary', ['title' => $snap['title'], 'cover' => $snap['cover'] ?? null, 'location' => $snap['location'], 'ref' => $snap['ref_code'],
        'lines' => [['Order', $order['order_number'], true], ['Package', $pkg['name'] ?? 'Standard'], ['Package total', money($order['package_total'], $order['currency_code'])], ['Due later', money(max(0, $order['package_total'] - $order['amount_due']), $order['currency_code'])]],
        'total' => ['Pay today', money($order['amount_due'], $order['currency_code'])]]); ?>
      <a href="<?= url('mpesa.php', ['order' => $order['uuid']]) ?>" class="vk-btn-accent w-full !py-4 text-base" id="pay-btn"><i class="fa-solid fa-mobile-screen-button"></i>Pay <?= e(money($order['amount_due'], $order['currency_code'])) ?></a>
      <button type="button" class="w-full text-center text-sm text-muted hover:text-rose-500" id="cancel-order"><i class="fa-solid fa-xmark"></i> Cancel order</button>
    </aside>
  </div>
</div>
<?php ob_start(); ?>
<script>
$('#cancel-order').on('click', () => VK.confirm({ icon: 'warning', title: 'Cancel this order?', confirmButtonText: 'Cancel order', cancelButtonText: 'Keep it' })
  .then(r => r && VK.api('orders.php', { action: 'cancel', order: <?= json_encode($order['uuid']) ?> }).then(x => { VK.toast(x.message); location.href = x.redirect; })));
</script>
<?php $extraScripts = ob_get_clean(); include APP_ROOT . '/includes/layout/footer.php'; ?>
