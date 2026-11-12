<?php
/** Order details + "Order placed" confirmation (step 4). */
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$o = one("SELECT * FROM orders WHERE uuid = ? AND user_id = ?", [(string)($_GET['id'] ?? ''), $me['id']]);
if (!$o) { flash('error', 'Order not found.'); redirect('orders.php'); }
$s = json_decode($o['listing_snapshot'], true);
$p = $o['package_snapshot'] ? json_decode($o['package_snapshot'], true) : null;
$pay = one("SELECT p.*, m.name AS method FROM payments p JOIN payment_methods m ON m.id = p.payment_method_id WHERE p.order_id = ? AND p.status = 'success' ORDER BY p.id DESC LIMIT 1", [$o['id']]);
$history = all("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY id", [$o['id']]);
$email = one("SELECT id, to_email, status, created_at FROM email_outbox WHERE related_type = 'order' AND related_id = ? ORDER BY id DESC LIMIT 1", [$o['id']]);
$placed = !empty($_GET['placed']) && $o['status'] === 'paid';

$pageTitle = 'Order ' . $o['order_number'];
include APP_ROOT . '/includes/layout/header.php';
?>
<div class="mx-auto max-w-5xl px-4 sm:px-6 pt-8">
  <?php if ($placed): component('checkout_steps', ['step' => 5]); ?>
    <section class="relative overflow-hidden rounded-4xl vk-hero text-white p-8 sm:p-10 text-center mb-6">
      <canvas id="confetti" class="pointer-events-none absolute inset-0 h-full w-full"></canvas>
      <span class="relative mx-auto grid h-20 w-20 place-items-center rounded-full bg-emerald-500 text-4xl shadow-lift"><i class="fa-solid fa-check"></i></span>
      <h1 class="relative mt-5 font-display text-3xl sm:text-4xl font-bold">Order placed!</h1>
      <p class="relative mt-2 text-slate-300 max-w-xl mx-auto">Your reservation for <b class="text-white"><?= e($s['title']) ?></b> is confirmed. We've emailed the order summary to <b class="text-white"><?= e($o['contact_email']) ?></b> and our property team has been alerted.</p>
      <div class="relative mt-5 inline-flex flex-wrap justify-center gap-2 text-sm">
        <span class="rounded-full bg-white/10 px-3 py-1.5"><i class="fa-solid fa-hashtag text-amber-400"></i> <?= e($o['order_number']) ?></span>
        <?php if ($pay): ?><span class="rounded-full bg-white/10 px-3 py-1.5"><i class="fa-solid fa-receipt text-amber-400"></i> <?= e($pay['provider_receipt']) ?></span><?php endif; ?>
        <span class="rounded-full bg-white/10 px-3 py-1.5"><i class="fa-solid fa-envelope-circle-check text-amber-400"></i> Email sent</span>
        <span class="rounded-full bg-white/10 px-3 py-1.5"><i class="fa-solid fa-building-circle-check text-amber-400"></i> PMS notified</span>
      </div>
    </section>
  <?php else: ?>
    <div class="flex flex-wrap items-center gap-3 mb-6">
      <a href="<?= url('orders.php') ?>" class="vk-icon-btn" aria-label="Back"><i class="fa-solid fa-arrow-left"></i></a>
      <div><h1 class="font-display text-2xl font-bold">Order <?= e($o['order_number']) ?></h1><p class="text-sm text-muted">Placed <?= e(fdate($o['created_at'], 'j M Y, g:i a')) ?></p></div>
      <div class="ml-auto"><?= status_pill($o['status']) ?></div>
    </div>
  <?php endif; ?>

  <div class="grid lg:grid-cols-[1fr_360px] gap-6 items-start">
    <div class="space-y-6">
      <section class="vk-panel p-5 sm:p-7">
        <h2 class="vk-section-title mb-4"><i class="fa-solid fa-route"></i>What happens next</h2>
        <ol class="relative border-l-2 border-line ml-3 space-y-6">
          <?php
          $steps = [
            ['Reservation paid', 'Your reservation fee was received.', in_array($o['status'], ['paid', 'processing', 'completed'], true), 'fa-receipt'],
            ['Vikao calls you', 'Within 24 hours via your preferred ' . $o['preferred_contact'] . '.', in_array($o['status'], ['processing', 'completed'], true), 'fa-headset'],
            ['Viewing & documents', $o['preferred_viewing_date'] ? 'Preferred viewing ' . fdate($o['preferred_viewing_date'], 'D, j M') : 'We\'ll agree on a viewing date.', in_array($o['status'], ['completed'], true), 'fa-file-signature'],
            ['Keys handed over', 'Complete your package payments and move in.', $o['status'] === 'completed', 'fa-key'],
          ];
          foreach ($steps as [$t, $d, $done, $i]): ?>
            <li class="ml-6"><span class="absolute -left-[17px] grid h-8 w-8 place-items-center rounded-full <?= $done ? 'bg-emerald-500 text-white' : 'bg-surface2 text-muted border border-line' ?>"><i class="fa-solid <?= $i ?> text-xs"></i></span>
              <p class="font-bold <?= $done ? '' : 'text-inksoft' ?>"><?= e($t) ?></p><p class="text-sm text-muted"><?= e($d) ?></p></li>
          <?php endforeach; ?>
        </ol>
      </section>
      <section class="vk-panel p-5 sm:p-7">
        <h2 class="vk-section-title mb-4"><i class="fa-solid fa-address-card"></i>Contact details</h2>
        <div class="grid sm:grid-cols-2 gap-4 text-sm">
          <?php foreach ([['Name', $o['contact_name']], ['Email', $o['contact_email']], ['Phone', $o['contact_phone']], ['Preferred contact', ucfirst($o['preferred_contact'])], ['ID / Passport', $o['id_number'] ?: '—'], ['Viewing date', $o['preferred_viewing_date'] ? fdate($o['preferred_viewing_date']) : '—']] as [$k, $v]): ?>
            <div><p class="text-xs text-muted"><?= e($k) ?></p><p class="font-semibold break-all"><?= e($v) ?></p></div>
          <?php endforeach; ?>
          <?php if ($o['notes']): ?><div class="sm:col-span-2"><p class="text-xs text-muted">Notes</p><p><?= e($o['notes']) ?></p></div><?php endif; ?>
        </div>
      </section>
      <?php if ($history): ?>
      <section class="vk-panel p-5 sm:p-7">
        <h2 class="vk-section-title mb-4"><i class="fa-solid fa-clock-rotate-left"></i>Activity</h2>
        <ul class="space-y-2 text-sm">
          <?php foreach ($history as $h): ?><li class="flex gap-3"><span class="text-muted w-36 shrink-0"><?= e(fdate($h['created_at'], 'j M, g:i a')) ?></span><span><b><?= e(STATUS_META[$h['to_status']][0] ?? $h['to_status']) ?></b><?= $h['note'] ? ' · ' . e($h['note']) : '' ?></span></li><?php endforeach; ?>
          <?php if ($email): ?><li class="flex gap-3"><span class="text-muted w-36 shrink-0"><?= e(fdate($email['created_at'], 'j M, g:i a')) ?></span><span><b>Email</b> · Summary sent to <?= e($email['to_email']) ?> <a class="text-accent font-bold" target="_blank" href="<?= url('email-preview.php', ['id' => $email['id']]) ?>">(view)</a></span></li><?php endif; ?>
        </ul>
      </section>
      <?php endif; ?>
    </div>
    <aside class="lg:sticky lg:top-32 space-y-4">
      <?php component('order_summary', ['title' => $s['title'], 'cover' => $s['cover'] ?? null, 'location' => $s['location'], 'ref' => $s['ref_code'],
        'lines' => array_values(array_filter([['Order', $o['order_number'], true], ['Package', $p['name'] ?? 'Standard'], ['Package total', money($o['package_total'], $o['currency_code'])],
          $pay ? ['Paid via', $pay['method'] . ' · ' . preg_replace('/^\+254/', '0', (string)$pay['payer_phone'])] : null, $pay ? ['Receipt', $pay['provider_receipt']] : null])),
        'total' => [$o['amount_paid'] > 0 ? 'Paid' : 'Due', money($o['amount_paid'] > 0 ? $o['amount_paid'] : $o['amount_due'], $o['currency_code'])]]); ?>
      <a href="<?= url('ad.php', ['id' => $s['uuid']]) ?>" class="vk-btn-ghost w-full"><i class="fa-solid fa-building"></i>View property</a>
      <button type="button" class="vk-btn-ghost w-full" onclick="window.print()"><i class="fa-solid fa-print"></i>Print receipt</button>
      <?php if ($o['status'] === 'pending_payment'): ?><a href="<?= url('checkout.php', ['order' => $o['uuid']]) ?>" class="vk-btn-accent w-full"><i class="fa-solid fa-lock"></i>Complete payment</a><?php endif; ?>
    </aside>
  </div>
</div>
<?php ob_start(); if ($placed): ?>
<script>
(function () { // lightweight confetti
  const c = document.getElementById('confetti'), x = c.getContext('2d'); c.width = c.offsetWidth; c.height = c.offsetHeight;
  const cols = ['#F59E0B', '#D97706', '#FDFBF7', '#10B981', '#38BDF8'];
  const ps = Array.from({ length: 140 }, () => ({ x: Math.random() * c.width, y: -20 - Math.random() * c.height, r: 3 + Math.random() * 5, vy: 2 + Math.random() * 3, vx: -1 + Math.random() * 2, c: cols[Math.floor(Math.random() * cols.length)], a: Math.random() * 6 }));
  let f = 0; (function tick() { x.clearRect(0, 0, c.width, c.height); ps.forEach(p => { p.y += p.vy; p.x += p.vx; p.a += .1; x.fillStyle = p.c; x.save(); x.translate(p.x, p.y); x.rotate(p.a); x.fillRect(-p.r, -p.r / 2, p.r * 2, p.r); x.restore(); }); if (++f < 240) requestAnimationFrame(tick); else x.clearRect(0, 0, c.width, c.height); })();
})();
</script>
<?php endif; $extraScripts = ob_get_clean(); include APP_ROOT . '/includes/layout/footer.php'; ?>
