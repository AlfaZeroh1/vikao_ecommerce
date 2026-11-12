<?php
/** Mock PMS · Page 2: post / edit listings on the Official Vikao Store (reuses the Sell form in official mode). */
require __DIR__ . '/_gate.php';
pms_require();

if (empty($_GET['edit']) && empty($_GET['new'])) {
    // list official listings + "new" button
    $rows = listing_cards("l.is_official = 1", [], 'l.published_at DESC', 100);
    $orderCounts = [];
    foreach (all("SELECT listing_id, COUNT(*) n FROM orders WHERE status IN ('paid','processing','completed') GROUP BY listing_id") as $r) $orderCounts[$r['listing_id']] = (int)$r['n'];
    $pageTitle = 'PMS · Official store';
    include APP_ROOT . '/includes/layout/header.php';
    component('pms_nav', ['active' => 'post']);
    component('page_header', ['icon' => 'fa-solid fa-crown', 'title' => 'Official Vikao Store', 'subtitle' => 'Listings managed by the Vikao agency. They publish instantly with a reservation fee and packages.',
        'actions' => '<a href="' . url('pms/post.php', ['new' => 1]) . '" class="vk-btn-accent"><i class="fa-solid fa-plus"></i>New official listing</a>']); ?>
    <div class="mx-auto max-w-7xl px-4 sm:px-6">
      <div class="vk-panel overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-left text-xs uppercase tracking-wider text-muted border-b border-line"><tr><th class="p-4">Listing</th><th class="p-4">Price</th><th class="p-4">Reservation</th><th class="p-4">PMS unit</th><th class="p-4">Views</th><th class="p-4">Paid orders</th><th class="p-4">Status</th><th class="p-4"></th></tr></thead>
          <tbody class="divide-y divide-line/60">
            <?php foreach ($rows as $l): $x = one("SELECT pms_unit_id FROM listings WHERE id = ?", [$l['id']]); ?>
              <tr class="hover:bg-surface2/50">
                <td class="p-4"><div class="flex items-center gap-3 min-w-[260px]"><img src="<?= e(media_url($l['cover_thumb'])) ?>" class="h-12 w-16 rounded-xl object-cover" alt=""><div class="min-w-0"><a href="<?= url('ad.php', ['id' => $l['uuid']]) ?>" target="_blank" class="font-bold hover:text-accent line-clamp-1"><?= e($l['title']) ?></a><p class="text-xs text-muted"><?= e($l['ref_code']) ?> · <?= e($l['location_name']) ?></p></div></div></td>
                <td class="p-4 font-bold whitespace-nowrap"><?= e(money($l['price'], $l['currency_code'], true)) ?><span class="text-xs text-muted font-normal"><?= e(price_suffix($l)) ?></span></td>
                <td class="p-4 whitespace-nowrap"><?= e(money($l['reservation_fee'], $l['currency_code'])) ?></td>
                <td class="p-4"><code class="text-xs"><?= e($x['pms_unit_id'] ?: '—') ?></code></td>
                <td class="p-4"><?= number_format((int)$l['views_count']) ?></td>
                <td class="p-4"><?= $orderCounts[$l['id']] ?? 0 ?></td>
                <td class="p-4"><?= status_pill($l['status']) ?></td>
                <td class="p-4 whitespace-nowrap text-right">
                  <a href="<?= url('pms/post.php', ['edit' => $l['uuid']]) ?>" class="vk-btn-ghost !py-1.5 !px-3 text-xs"><i class="fa-solid fa-pen"></i>Edit</a>
                  <button type="button" class="vk-btn-ghost !py-1.5 !px-3 text-xs" data-toggle-official="<?= e($l['uuid']) ?>" data-status="<?= e($l['status']) ?>"><i class="fa-solid <?= $l['status'] === 'active' ? 'fa-eye-slash' : 'fa-eye' ?>"></i><?= $l['status'] === 'active' ? 'Unpublish' : 'Publish' ?></button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php if (!$rows): ?><div class="p-8"><?php component('empty_state', ['icon' => 'fa-crown', 'title' => 'No official listings yet', 'cta' => 'Create one', 'href' => url('pms/post.php', ['new' => 1])]); ?></div><?php endif; ?>
      </div>
    </div>
    <?php ob_start(); ?>
    <script>
    $('[data-toggle-official]').on('click', function () {
      VK.api('../pms/api.php', { action: 'toggle_official', listing: $(this).data('toggle-official') }).then(r => { VK.toast(r.message); setTimeout(() => location.reload(), 400); });
    });
    </script>
    <?php $extraScripts = ob_get_clean(); include APP_ROOT . '/includes/layout/footer.php';
    exit;
}

// create / edit form → the shared Sell form in official mode
$officialMode = true;
require APP_ROOT . '/sell.php';
