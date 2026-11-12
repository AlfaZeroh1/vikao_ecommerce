<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$tab = in_array($_GET['tab'] ?? '', ['active', 'pending', 'rejected', 'closed'], true) ? $_GET['tab'] : 'all';
$counts = ['all' => 0, 'active' => 0, 'pending' => 0, 'rejected' => 0, 'closed' => 0];
foreach (all("SELECT status, COUNT(*) n FROM listings WHERE user_id = ? AND deleted_at IS NULL GROUP BY status", [$me['id']]) as $r) {
    $k = in_array($r['status'], ['closed', 'sold', 'expired'], true) ? 'closed' : $r['status'];
    if (isset($counts[$k])) $counts[$k] += (int)$r['n'];
    $counts['all'] += (int)$r['n'];
}
$statusSql = ['all' => '1=1', 'active' => "l.status = 'active'", 'pending' => "l.status = 'pending'", 'rejected' => "l.status = 'rejected'", 'closed' => "l.status IN ('closed','sold','expired')"][$tab];
$rows = listing_cards("l.user_id = :u AND $statusSql", ['u' => $me['id']], 'l.updated_at DESC', 200);
$extra = [];
foreach ($rows as $r) $extra[$r['id']] = one("SELECT l.chats_count, l.phone_reveals_count, l.expires_at, l.closed_at, l.close_reason, l.submitted_at,
    (SELECT reason FROM listing_reviews rv WHERE rv.listing_id = l.id AND rv.decision = 'rejected' ORDER BY rv.id DESC LIMIT 1) AS reject_reason
    FROM listings l WHERE l.id = ?", [$r['id']]);
$highlight = (string)($_GET['submitted'] ?? '');
$totals = one("SELECT COALESCE(SUM(views_count),0) v, COALESCE(SUM(favourites_count),0) f, COALESCE(SUM(chats_count),0) c, COALESCE(SUM(phone_reveals_count),0) p FROM listings WHERE user_id = ? AND deleted_at IS NULL", [$me['id']]);

$pageTitle = 'My listings';
include APP_ROOT . '/includes/layout/header.php';
component('page_header', ['icon' => 'fa-solid fa-rectangle-list', 'title' => 'My listings', 'subtitle' => 'Edit, close or re-open your ads. Edits are reviewed before going live.',
    'actions' => '<a href="' . url('sell.php') . '" class="vk-btn-accent"><i class="fa-solid fa-plus"></i>Post new ad</a>']);
?>
<div class="mx-auto max-w-7xl px-4 sm:px-6 space-y-5">
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    <?php foreach ([['fa-eye', 'Total views', $totals['v'], 'text-sky-500'], ['fa-heart', 'Saved by buyers', $totals['f'], 'text-rose-500'], ['fa-comments', 'Chats started', $totals['c'], 'text-violet-500'], ['fa-phone', 'Phone reveals', $totals['p'], 'text-emerald-500']] as [$i, $lab, $n, $c]): ?>
      <div class="vk-panel p-4 flex items-center gap-3"><span class="grid h-11 w-11 place-items-center rounded-xl bg-surface2 <?= $c ?>"><i class="fa-solid <?= $i ?>"></i></span><div><p class="text-xl font-extrabold"><?= number_format((int)$n) ?></p><p class="text-xs text-muted"><?= $lab ?></p></div></div>
    <?php endforeach; ?>
  </div>

  <div class="flex gap-2 overflow-x-auto no-scrollbar">
    <?php foreach (['all' => ['All', 'fa-layer-group'], 'active' => ['Active', 'fa-circle-check'], 'pending' => ['Under review', 'fa-hourglass-half'], 'rejected' => ['Declined', 'fa-circle-xmark'], 'closed' => ['Closed', 'fa-lock']] as $k => [$lab, $i]): ?>
      <a href="<?= url('my-listings.php', ['tab' => $k === 'all' ? null : $k]) ?>" class="<?= $tab === $k ? 'vk-btn-dark' : 'vk-btn-ghost' ?> !py-2.5 shrink-0"><i class="fa-solid <?= $i ?>"></i><?= $lab ?><span class="rounded-full <?= $tab === $k ? 'bg-white/15' : 'bg-surface2' ?> px-2 text-xs"><?= $counts[$k] ?></span></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$rows): component('empty_state', ['icon' => 'fa-rectangle-list', 'title' => 'No ads here yet', 'text' => 'Post your first property or service — it\'s free and takes minutes.', 'cta' => 'Post an ad', 'href' => url('sell.php')]); endif; ?>

  <div class="space-y-3">
    <?php foreach ($rows as $l): $x = $extra[$l['id']]; $hl = $highlight === $l['uuid']; ?>
      <article class="vk-panel p-3 sm:p-4 flex flex-col sm:flex-row gap-4 <?= $hl ? 'ring-2 ring-accent' : '' ?>" data-row="<?= e($l['uuid']) ?>">
        <a href="<?= url('ad.php', ['id' => $l['uuid']]) ?>" class="relative shrink-0 sm:w-56 aspect-[4/3] overflow-hidden rounded-2xl bg-surface2">
          <img src="<?= e(media_url($l['cover_thumb'])) ?>" alt="" class="h-full w-full object-cover">
          <?php if ($l['is_official']): ?><span class="absolute top-2 left-2 vk-badge-official"><i class="fa-solid fa-crown"></i>Official</span><?php endif; ?>
        </a>
        <div class="flex-1 min-w-0 flex flex-col">
          <div class="flex flex-wrap items-center gap-2">
            <?= status_pill($l['status']) ?>
            <span class="text-xs text-muted"><?= e($l['ref_code']) ?> · <?= e($l['category_name']) ?></span>
            <?php if ($hl): ?><span class="text-xs font-bold text-accent"><i class="fa-solid fa-paper-plane"></i> Just submitted</span><?php endif; ?>
          </div>
          <a href="<?= url('ad.php', ['id' => $l['uuid']]) ?>" class="mt-2 font-bold text-lg leading-snug hover:text-accent line-clamp-2"><?= e($l['title']) ?></a>
          <p class="text-accent font-extrabold"><?= e(money($l['price'], $l['currency_code'])) ?> <span class="text-xs text-muted font-semibold"><?= e(price_suffix($l)) ?></span></p>
          <?php if ($l['status'] === 'rejected' && $x['reject_reason']): ?>
            <p class="mt-2 rounded-xl bg-rose-500/10 px-3 py-2 text-sm text-rose-700 dark:text-rose-300"><i class="fa-solid fa-circle-exclamation"></i> <b>Reviewer note:</b> <?= e($x['reject_reason']) ?></p>
          <?php elseif ($l['status'] === 'pending'): ?>
            <p class="mt-2 text-sm text-muted"><i class="fa-solid fa-hourglass-half text-amber-500"></i> Submitted <?= e(time_ago($x['submitted_at'])) ?> — we'll notify you once it's reviewed.</p>
          <?php elseif (in_array($l['status'], ['closed', 'sold'], true)): ?>
            <p class="mt-2 text-sm text-muted"><i class="fa-solid fa-lock"></i> Closed <?= e(time_ago($x['closed_at'])) ?><?= $x['close_reason'] ? ' · ' . e($x['close_reason']) : '' ?></p>
          <?php endif; ?>
          <div class="mt-auto pt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs font-semibold text-muted">
            <span><i class="fa-regular fa-eye"></i> <?= number_format((int)$l['views_count']) ?> views</span>
            <span><i class="fa-regular fa-heart"></i> <?= (int)$l['favourites_count'] ?></span>
            <span><i class="fa-regular fa-comments"></i> <?= (int)$x['chats_count'] ?></span>
            <span><i class="fa-solid fa-phone"></i> <?= (int)$x['phone_reveals_count'] ?></span>
            <?php if ($l['status'] === 'active' && $x['expires_at']): ?><span><i class="fa-regular fa-calendar"></i> Expires <?= e(fdate($x['expires_at'])) ?></span><?php endif; ?>
          </div>
        </div>
        <div class="flex sm:flex-col gap-2 sm:w-40 shrink-0">
          <a href="<?= url('ad.php', ['id' => $l['uuid']]) ?>" class="vk-btn-ghost !py-2 flex-1"><i class="fa-regular fa-eye"></i>View</a>
          <?php if (!$l['is_official']): ?>
            <a href="<?= url('sell.php', ['edit' => $l['uuid']]) ?>" class="vk-btn-ghost !py-2 flex-1"><i class="fa-solid fa-pen"></i>Edit</a>
            <?php if (in_array($l['status'], ['active', 'pending'], true)): ?>
              <button type="button" class="vk-btn-ghost !py-2 flex-1 hover:!text-rose-600" data-close="<?= e($l['uuid']) ?>"><i class="fa-solid fa-lock"></i>Close</button>
            <?php else: ?>
              <button type="button" class="vk-btn-dark !py-2 flex-1" data-reopen="<?= e($l['uuid']) ?>"><i class="fa-solid fa-rotate"></i>Re-open</button>
            <?php endif; ?>
            <button type="button" class="vk-btn-ghost !py-2 flex-1 !text-rose-600" data-delete="<?= e($l['uuid']) ?>" title="Delete"><i class="fa-regular fa-trash-can"></i><span class="sm:inline hidden">Delete</span></button>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</div>
<?php ob_start(); ?>
<script>
$('[data-close]').on('click', function () {
  const id = $(this).data('close');
  Swal.fire({ title: 'Close this ad?', text: 'It will no longer be visible to buyers. You can re-open it later.', icon: 'question',
    input: 'radio', inputOptions: { 'Sold / let on Vikao': 'Sold / let on Vikao', 'Sold / let elsewhere': 'Sold / let elsewhere', 'No longer available': 'No longer available' },
    inputValidator: v => !v && 'Tell us why', showCancelButton: true, confirmButtonText: 'Close ad' })
    .then(r => { if (r.isConfirmed) VK.api('listings.php', { action: 'close', listing: id, reason: r.value, sold: r.value.startsWith('Sold') ? 1 : 0 }).then(x => { VK.toast(x.message); setTimeout(() => location.reload(), 600); }); });
});
$('[data-reopen]').on('click', function () {
  const id = $(this).data('reopen');
  VK.confirm({ title: 'Re-open this ad?', text: 'It will be re-submitted to Vikao for a quick review.', confirmButtonText: 'Re-open' })
    .then(r => r && VK.api('listings.php', { action: 'reopen', listing: id }).then(x => { VK.toast(x.message); setTimeout(() => location.reload(), 600); }));
});
$('[data-delete]').on('click', function () {
  const id = $(this).data('delete');
  VK.confirm({ icon: 'warning', title: 'Delete permanently?', text: 'This removes the ad from your account. This cannot be undone.', confirmButtonText: 'Delete' })
    .then(r => r && VK.api('listings.php', { action: 'delete', listing: id }).then(x => { VK.toast(x.message); $('[data-row="' + id + '"]').fadeOut(); }));
});
</script>
<?php $extraScripts = ob_get_clean(); include APP_ROOT . '/includes/layout/footer.php'; ?>
