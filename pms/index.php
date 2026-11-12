<?php
/** Mock PMS · Page 1: review & approve / decline ads + incoming alerts (orders, submissions). */
require __DIR__ . '/_gate.php';
$me = pms_require();
$tab = in_array($_GET['tab'] ?? '', ['approved', 'rejected'], true) ? $_GET['tab'] : 'pending';
$queue = all("SELECT l.*, u.display_name AS seller, u.email AS seller_email, u.phone AS seller_phone, c.name AS category_name, c.icon AS category_icon,
        loc.full_name AS location_full, m.thumb_path,
        (SELECT COUNT(*) FROM listing_media x WHERE x.listing_id = l.id) AS photos,
        rv.id AS review_id, rv.submission_type, rv.submitted_at AS rv_submitted, rv.decided_at, rv.reason, rv.decision
    FROM listing_reviews rv
    JOIN listings l ON l.id = rv.listing_id
    JOIN users u ON u.id = l.user_id JOIN categories c ON c.id = l.category_id JOIN locations loc ON loc.id = l.location_id
    LEFT JOIN listing_media m ON m.id = l.cover_media_id
    WHERE rv.decision = ? AND l.deleted_at IS NULL AND l.is_official = 0
    ORDER BY " . ($tab === 'pending' ? 'rv.submitted_at ASC' : 'rv.decided_at DESC') . " LIMIT 100", [$tab]);
$counts = [];
foreach (all("SELECT rv.decision, COUNT(*) n FROM listing_reviews rv JOIN listings l ON l.id = rv.listing_id WHERE l.is_official = 0 AND l.deleted_at IS NULL GROUP BY rv.decision") as $r) $counts[$r['decision']] = (int)$r['n'];
$events = all("SELECT * FROM pms_events ORDER BY id DESC LIMIT 25");
$orders = all("SELECT o.order_number, o.uuid, o.contact_name, o.contact_phone, o.amount_paid, o.currency_code, o.status, o.paid_at, o.listing_snapshot FROM orders o WHERE o.status <> 'pending_payment' ORDER BY o.id DESC LIMIT 8");
$kpi = one("SELECT (SELECT COUNT(*) FROM listings WHERE status='active' AND deleted_at IS NULL) active,
    (SELECT COUNT(*) FROM listings WHERE status='pending' AND deleted_at IS NULL) pending,
    (SELECT COUNT(*) FROM orders WHERE status IN ('paid','processing','completed')) paid_orders,
    (SELECT COALESCE(SUM(amount_paid),0) FROM orders WHERE status IN ('paid','processing','completed')) revenue,
    (SELECT COUNT(*) FROM listing_reports WHERE status='open') reports");
$reasons = ['Photos are unclear or contain watermarks / contact details', 'Price looks incorrect', 'Wrong category selected', 'Description is incomplete', 'Duplicate of an existing ad', 'Suspected scam / unverifiable ownership'];

$pageTitle = 'PMS · Ad review';
include APP_ROOT . '/includes/layout/header.php';
component('pms_nav', ['active' => 'review']);
?>
<div class="mx-auto max-w-7xl px-4 sm:px-6 pt-6 space-y-6">
  <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
    <?php foreach ([['fa-hourglass-half', 'Awaiting review', $kpi['pending'], 'text-amber-500'], ['fa-circle-check', 'Live ads', $kpi['active'], 'text-emerald-500'], ['fa-bag-shopping', 'Paid reservations', $kpi['paid_orders'], 'text-sky-500'], ['fa-sack-dollar', 'Reservation revenue', money($kpi['revenue'], 'KES', true), 'text-accent'], ['fa-flag', 'Open reports', $kpi['reports'], 'text-rose-500']] as [$i, $lab, $n, $c]): ?>
      <div class="vk-panel p-4"><i class="fa-solid <?= $i ?> <?= $c ?>"></i><p class="mt-2 text-2xl font-extrabold"><?= is_numeric($n) ? number_format((float)$n) : e($n) ?></p><p class="text-xs text-muted font-semibold"><?= $lab ?></p></div>
    <?php endforeach; ?>
  </div>

  <div class="grid xl:grid-cols-[1fr_380px] gap-6 items-start">
    <section>
      <div class="flex items-center gap-2 mb-4">
        <h1 class="font-display text-2xl font-bold mr-auto">Ad review queue</h1>
        <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Declined'] as $k => $lab): ?>
          <a href="<?= url('pms/index.php', ['tab' => $k === 'pending' ? null : $k]) ?>" class="<?= $tab === $k ? 'vk-btn-dark' : 'vk-btn-ghost' ?> !py-2 text-sm"><?= $lab ?> <span class="text-xs opacity-70"><?= $counts[$k] ?? 0 ?></span></a>
        <?php endforeach; ?>
      </div>
      <?php if (!$queue): component('empty_state', ['icon' => 'fa-mug-hot', 'title' => $tab === 'pending' ? 'Queue is empty' : 'Nothing here', 'text' => $tab === 'pending' ? 'All submitted ads have been reviewed. Nice work!' : '']); endif; ?>
      <div class="space-y-3">
        <?php foreach ($queue as $q): ?>
          <article class="vk-panel p-4 flex flex-col md:flex-row gap-4" data-review="<?= e($q['uuid']) ?>">
            <a href="<?= url('ad.php', ['id' => $q['uuid']]) ?>" target="_blank" class="relative md:w-48 shrink-0 aspect-[4/3] overflow-hidden rounded-2xl bg-surface2">
              <img src="<?= e(media_url($q['thumb_path'])) ?>" class="h-full w-full object-cover" alt="">
              <span class="absolute bottom-2 left-2 vk-badge bg-slate-900/75 text-white"><i class="fa-solid fa-camera"></i><?= (int)$q['photos'] ?></span>
            </a>
            <div class="flex-1 min-w-0">
              <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="vk-badge <?= ['new' => 'bg-sky-500/15 text-sky-600', 'edit' => 'bg-violet-500/15 text-violet-600', 'resubmit' => 'bg-amber-500/15 text-amber-700'][$q['submission_type']] ?>"><i class="fa-solid <?= ['new' => 'fa-plus', 'edit' => 'fa-pen', 'resubmit' => 'fa-rotate'][$q['submission_type']] ?>"></i><?= ['new' => 'New ad', 'edit' => 'Edited', 'resubmit' => 'Re-opened'][$q['submission_type']] ?></span>
                <span class="text-muted"><i class="fa-solid <?= e($q['category_icon']) ?>"></i> <?= e($q['category_name']) ?></span>
                <span class="text-muted">· <?= e($q['ref_code']) ?></span>
                <span class="ml-auto text-muted"><i class="fa-regular fa-clock"></i> <?= e(time_ago($tab === 'pending' ? $q['rv_submitted'] : $q['decided_at'])) ?></span>
              </div>
              <a href="<?= url('ad.php', ['id' => $q['uuid']]) ?>" target="_blank" class="mt-1.5 block font-bold text-lg leading-snug hover:text-accent"><?= e($q['title']) ?> <i class="fa-solid fa-arrow-up-right-from-square text-xs text-muted"></i></a>
              <p class="text-accent font-extrabold"><?= e(money($q['price'], $q['currency_code'])) ?> <span class="text-xs text-muted font-semibold"><?= e(price_suffix($q)) ?></span></p>
              <p class="text-sm text-muted truncate"><i class="fa-solid fa-location-dot"></i> <?= e($q['location_full']) ?></p>
              <p class="mt-2 text-sm text-inksoft line-clamp-2"><?= e(excerpt($q['description_text'], 220)) ?></p>
              <p class="mt-2 text-xs text-muted"><i class="fa-solid fa-user"></i> <?= e($q['seller']) ?> · <?= e($q['seller_email']) ?> · <?= e($q['seller_phone']) ?></p>
              <?php if ($tab === 'rejected' && $q['reason']): ?><p class="mt-2 text-sm text-rose-600"><i class="fa-solid fa-circle-xmark"></i> <?= e($q['reason']) ?></p><?php endif; ?>
            </div>
            <?php if ($tab === 'pending'): ?>
              <div class="flex md:flex-col gap-2 md:w-40 shrink-0">
                <button type="button" class="vk-btn flex-1 bg-emerald-600 text-white hover:brightness-110" data-approve><i class="fa-solid fa-check"></i>Approve</button>
                <button type="button" class="vk-btn-ghost flex-1 !text-rose-600" data-reject><i class="fa-solid fa-xmark"></i>Decline</button>
              </div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <aside class="space-y-6 xl:sticky xl:top-32">
      <div class="vk-panel overflow-hidden">
        <div class="flex items-center gap-2 px-5 py-4 border-b border-line"><i class="fa-solid fa-satellite-dish text-accent"></i><h2 class="font-bold">PMS alerts</h2><span class="ml-auto text-xs text-muted">live outbox</span></div>
        <ul class="max-h-[420px] overflow-y-auto divide-y divide-line/60">
          <?php if (!$events): ?><li class="p-5 text-sm text-muted">No events yet.</li><?php endif; ?>
          <?php foreach ($events as $ev): $pl = json_decode($ev['payload'], true); $isOrder = str_starts_with($ev['event_type'], 'order.'); ?>
            <li class="p-4 text-sm <?= $ev['status'] === 'delivered' ? 'bg-accent/[.04]' : '' ?>" data-event="<?= (int)$ev['id'] ?>">
              <div class="flex items-center gap-2">
                <span class="grid h-8 w-8 place-items-center rounded-lg <?= $isOrder ? 'bg-emerald-500/15 text-emerald-600' : 'bg-sky-500/15 text-sky-600' ?>"><i class="fa-solid <?= $isOrder ? 'fa-sack-dollar' : 'fa-file-circle-plus' ?>"></i></span>
                <code class="text-xs font-bold"><?= e($ev['event_type']) ?></code>
                <span class="ml-auto text-[11px] text-muted"><?= e(time_ago($ev['created_at'])) ?></span>
              </div>
              <?php if ($isOrder): ?>
                <p class="mt-2"><b><?= e($pl['order_number']) ?></b> · <?= e(money($pl['amount_paid'], $pl['currency'])) ?> · <?= e($pl['mpesa_receipt']) ?></p>
                <p class="text-muted"><?= e($pl['customer']['name']) ?> · <?= e($pl['customer']['phone']) ?> · prefers <?= e($pl['customer']['preferred_contact']) ?></p>
                <p class="text-muted truncate"><?= e($pl['listing']['title'] ?? '') ?><?= !empty($pl['package']['name']) ? ' · ' . e($pl['package']['name']) : '' ?></p>
              <?php else: ?>
                <p class="mt-2 text-muted truncate"><?= e($pl['title'] ?? ('Listing #' . $ev['aggregate_id'])) ?></p>
              <?php endif; ?>
              <?php if ($ev['status'] === 'delivered'): ?><button type="button" class="mt-2 text-xs font-bold text-accent" data-ack><i class="fa-solid fa-check"></i> Acknowledge</button>
              <?php else: ?><p class="mt-2 text-[11px] text-emerald-600 font-bold"><i class="fa-solid fa-check-double"></i> Acknowledged</p><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="vk-panel overflow-hidden">
        <div class="flex items-center gap-2 px-5 py-4 border-b border-line"><i class="fa-solid fa-bag-shopping text-accent"></i><h2 class="font-bold">Recent reservations</h2></div>
        <ul class="divide-y divide-line/60 text-sm">
          <?php if (!$orders): ?><li class="p-5 text-muted">No paid reservations yet.</li><?php endif; ?>
          <?php foreach ($orders as $o): $s = json_decode($o['listing_snapshot'], true); ?>
            <li class="p-4"><div class="flex items-center gap-2"><b class="font-mono text-xs"><?= e($o['order_number']) ?></b><span class="ml-auto"><?= status_pill($o['status']) ?></span></div>
              <p class="mt-1 truncate"><?= e($s['title'] ?? '') ?></p><p class="text-muted text-xs"><?= e($o['contact_name']) ?> · <?= e($o['contact_phone']) ?> · <?= e(money($o['amount_paid'], $o['currency_code'])) ?></p></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </aside>
  </div>
</div>
<?php ob_start(); ?>
<script>
const PMS = (data) => VK.api('../pms/api.php', data);
$('[data-approve]').on('click', function () {
  const $r = $(this).closest('[data-review]');
  PMS({ action: 'approve', listing: $r.data('review') }).then(x => { VK.toast(x.message); $r.slideUp(200); });
});
$('[data-reject]').on('click', function () {
  const $r = $(this).closest('[data-review]');
  Swal.fire({ title: 'Decline this ad', input: 'select', inputOptions: <?= json_encode(array_combine($reasons, $reasons) + ['__other' => 'Other (type reason)…']) ?>, inputPlaceholder: 'Choose a reason',
    showCancelButton: true, confirmButtonText: 'Continue', inputValidator: v => !v && 'Pick a reason' })
  .then(r => {
    if (!r.isConfirmed) return null;
    if (r.value !== '__other') return r.value;
    return Swal.fire({ title: 'Reason', input: 'textarea', inputPlaceholder: 'Explain what the seller should fix', showCancelButton: true, inputValidator: v => (!v || v.length < 5) && 'Write a short reason' }).then(x => x.isConfirmed ? x.value : null);
  })
  .then(reason => { if (reason) PMS({ action: 'reject', listing: $r.data('review'), reason }).then(x => { VK.toast(x.message, 'info'); $r.slideUp(200); }); });
});
$('[data-ack]').on('click', function () {
  const $e = $(this).closest('[data-event]');
  PMS({ action: 'ack', id: $e.data('event') }).then(() => { $(this).replaceWith('<p class="mt-2 text-[11px] text-emerald-600 font-bold"><i class="fa-solid fa-check-double"></i> Acknowledged</p>'); $e.removeClass('bg-accent/[.04]'); });
});
</script>
<?php $extraScripts = ob_get_clean(); include APP_ROOT . '/includes/layout/footer.php'; ?>
