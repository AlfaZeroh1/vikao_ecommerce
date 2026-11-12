<?php
/** Order page (step 1): choose package + contact info for an Official Vikao listing. */
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$row = find_listing((string)($_GET['id'] ?? ''));
if (!$row || !$row['is_official'] || $row['status'] !== 'active') { flash('error', 'This property is not available for reservation.'); redirect('shop.php?official=1'); }
$l = load_listing_full($row);
$loc = implode(', ', array_reverse(array_column($l['location_chain'], 'name')));
$cover = $l['media'][0]['thumb_path'] ?? null;
$pending = one("SELECT * FROM orders WHERE user_id = ? AND listing_id = ? AND status = 'pending_payment' ORDER BY id DESC LIMIT 1", [$me['id'], $l['id']]);
$prefPkg = $pending['package_id'] ?? (array_values(array_filter($l['packages'], fn($p) => $p['is_recommended']))[0]['id'] ?? ($l['packages'][0]['id'] ?? null));

$pageTitle = 'Reserve · ' . $l['title'];
include APP_ROOT . '/includes/layout/header.php';
?>
<div class="mx-auto max-w-6xl px-4 sm:px-6 pt-8">
  <?php component('checkout_steps', ['step' => 1]); ?>
  <h1 class="font-display text-2xl sm:text-3xl font-bold">Reserve this property</h1>
  <p class="text-muted mb-6">Choose a package and tell us how to reach you. You'll pay only the reservation fee today.</p>
  <form id="order-form" class="grid lg:grid-cols-[1fr_380px] gap-6 items-start" novalidate>
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="listing" value="<?= e($l['uuid']) ?>">
    <div class="space-y-6">
      <?php if ($l['packages']): ?>
      <section class="vk-panel p-5 sm:p-7">
        <h2 class="vk-section-title mb-4"><i class="fa-solid fa-box-open"></i>Choose a package</h2>
        <div class="grid sm:grid-cols-2 gap-3" data-field="package_id" data-field-wrap>
          <?php foreach ($l['packages'] as $p): ?>
            <label class="relative cursor-pointer">
              <input type="radio" name="package_id" value="<?= $p['id'] ?>" class="peer sr-only" <?= (int)$prefPkg === (int)$p['id'] ? 'checked' : '' ?> data-total="<?= (float)$p['total_price'] ?>" data-name="<?= e($p['name']) ?>">
              <div class="h-full rounded-2xl border-2 border-line p-4 transition peer-checked:border-accent peer-checked:bg-accent/5 hover:border-accent/50">
                <div class="flex items-start gap-2">
                  <span class="grid h-10 w-10 place-items-center rounded-xl bg-surface2 text-accent"><i class="fa-solid <?= ['outright' => 'fa-money-bill-wave', 'installment' => 'fa-calendar-check', 'lease' => 'fa-file-signature', 'short_stay' => 'fa-bed', 'custom' => 'fa-building-columns'][$p['package_type']] ?? 'fa-box' ?>"></i></span>
                  <?php if ($p['is_recommended']): ?><span class="ml-auto vk-badge bg-emerald-500 text-white"><i class="fa-solid fa-thumbs-up"></i>Best value</span><?php endif; ?>
                </div>
                <p class="mt-3 font-bold"><?= e($p['name']) ?></p>
                <p class="text-xl font-extrabold text-accent"><?= e(money($p['total_price'], $l['currency_code'])) ?></p>
                <?php if ($p['description']): ?><p class="mt-1 text-sm text-muted"><?= e($p['description']) ?></p><?php endif; ?>
                <ul class="mt-2 space-y-1 text-xs text-inksoft">
                  <?php if ($p['deposit_amount']): ?><li><i class="fa-solid fa-circle-dot text-accent mr-1"></i>Deposit <?= e(money($p['deposit_amount'], $l['currency_code'])) ?></li><?php endif; ?>
                  <?php if ($p['installments']): ?><li><i class="fa-solid fa-circle-dot text-accent mr-1"></i><?= (int)$p['installments'] ?> × <?= e(money($p['installment_amount'], $l['currency_code'])) ?></li><?php endif; ?>
                  <?php if ($p['duration_months']): ?><li><i class="fa-solid fa-circle-dot text-accent mr-1"></i><?= (int)$p['duration_months'] ?> months</li><?php endif; ?>
                </ul>
              </div>
              <span class="absolute top-4 right-4 hidden peer-checked:grid h-6 w-6 place-items-center rounded-full bg-accent text-accentink text-xs"><i class="fa-solid fa-check"></i></span>
            </label>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <section class="vk-panel p-5 sm:p-7 space-y-4">
        <h2 class="vk-section-title"><i class="fa-solid fa-address-card"></i>Your contact details</h2>
        <p class="text-sm text-muted -mt-2">We'll use these to confirm your reservation and arrange viewing / paperwork.</p>
        <div class="grid sm:grid-cols-2 gap-3">
          <div data-field-wrap><label class="vk-label">Full name <span class="req">*</span></label><input name="contact_name" value="<?= e($pending['contact_name'] ?? trim($me['first_name'] . ' ' . $me['last_name'])) ?>" class="vk-input" autocomplete="name"></div>
          <div data-field-wrap><label class="vk-label">National ID / Passport</label><input name="id_number" value="<?= e($pending['id_number'] ?? '') ?>" class="vk-input" placeholder="optional"></div>
          <div data-field-wrap><label class="vk-label">Email <span class="req">*</span></label><input name="contact_email" type="email" value="<?= e($pending['contact_email'] ?? $me['email']) ?>" class="vk-input" autocomplete="email"></div>
          <div data-field-wrap><label class="vk-label">Phone <span class="req">*</span></label><input name="contact_phone" value="<?= e($pending['contact_phone'] ?? $me['phone']) ?>" class="vk-input" autocomplete="tel"></div>
        </div>
        <div data-field-wrap><label class="vk-label">Preferred contact method</label>
          <div class="flex flex-wrap gap-2">
            <?php foreach (['phone' => ['Phone call', 'fa-phone'], 'whatsapp' => ['WhatsApp', 'fa-whatsapp'], 'email' => ['Email', 'fa-envelope']] as $k => [$lab, $i]): ?>
              <label class="vk-choice"><input type="radio" name="preferred_contact" value="<?= $k ?>" <?= ($pending['preferred_contact'] ?? 'phone') === $k ? 'checked' : '' ?>><span><i class="<?= $k === 'whatsapp' ? 'fa-brands' : 'fa-solid' ?> <?= $i ?>"></i><?= $lab ?></span></label>
            <?php endforeach; ?>
          </div></div>
        <div class="grid sm:grid-cols-2 gap-3">
          <div data-field-wrap><label class="vk-label">Preferred viewing date</label><input type="date" name="viewing_date" min="<?= date('Y-m-d') ?>" value="<?= e($pending['preferred_viewing_date'] ?? '') ?>" class="vk-input"></div>
        </div>
        <div data-field-wrap><label class="vk-label">Notes for our team</label><textarea name="notes" rows="3" class="vk-textarea" maxlength="1000" placeholder="Anything we should know? e.g. financing, move-in date…"><?= e($pending['notes'] ?? '') ?></textarea></div>
        <div data-field-wrap>
          <label class="flex items-start gap-3 text-sm text-inksoft"><input type="checkbox" name="agree" value="1" class="mt-1 h-4 w-4 accent-amber-600">
            <span>I understand the reservation fee holds this property for <b>14 days</b>, is deducted from the final price, and is refundable if Vikao cannot complete the transaction.</span></label>
        </div>
      </section>
    </div>

    <aside class="lg:sticky lg:top-32 space-y-4">
      <?php component('order_summary', ['title' => $l['title'], 'cover' => $cover, 'location' => $loc, 'ref' => $l['ref_code'],
        'lines' => [['Listed price', money($l['price'], $l['currency_code']) . price_suffix($l)], ['Package', '—'], ['Package total', '—']],
        'total' => ['Pay today', money($l['reservation_fee'], $l['currency_code'])]]); ?>
      <button class="vk-btn-accent w-full !py-4 text-base" id="to-checkout"><i class="fa-solid fa-lock"></i>Continue to checkout</button>
      <a href="<?= url('ad.php', ['id' => $l['uuid']]) ?>" class="block text-center text-sm text-muted hover:text-accent"><i class="fa-solid fa-arrow-left"></i> Back to listing</a>
    </aside>
  </form>
</div>
<?php ob_start(); ?>
<script>
(function () {
  const $f = $('#order-form');
  function sync() {
    const $p = $('input[name=package_id]:checked');
    const $dd = $('aside dl dd');
    if ($p.length) { $dd.eq(1).text($p.data('name')); $dd.eq(2).text(VK.money($p.data('total'))); }
  }
  $f.on('change', 'input[name=package_id]', sync); sync();
  $f.on('submit', function (e) {
    e.preventDefault(); const $b = $('#to-checkout'); VK.btnLoading($b, true, 'Creating order…');
    VK.api('orders.php', $f.serialize(), { silent: true }).then(r => { VK.toast(r.message); location.href = r.redirect; })
      .catch(j => { VK.btnLoading($b, false); VK.showErrors($f, j.errors); VK.toast(j.message, 'error'); });
  });
})();
</script>
<?php $extraScripts = ob_get_clean(); include APP_ROOT . '/includes/layout/footer.php'; ?>
