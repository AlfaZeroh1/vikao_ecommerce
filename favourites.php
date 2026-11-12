<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$rows = all("SELECT " . CARD_SELECT . ", f.created_at AS saved_at " . CARD_JOINS . " JOIN favourites f ON f.listing_id = l.id AND f.user_id = :u ORDER BY (l.status = 'active') DESC, f.created_at DESC", ['u' => $me['id']]);
$inactive = count(array_filter($rows, fn($r) => $r['status'] !== 'active'));

$pageTitle = 'Favourites';
include APP_ROOT . '/includes/layout/header.php';
component('page_header', ['icon' => 'fa-solid fa-heart', 'title' => 'Favourites', 'subtitle' => plural(count($rows), 'saved ad') . ($inactive ? ' · ' . $inactive . ' no longer active' : ''),
    'actions' => $inactive ? '<button type="button" class="vk-btn-ghost" id="clear-inactive"><i class="fa-solid fa-broom"></i>Remove closed ads</button>' : '']);
?>
<div class="mx-auto max-w-7xl px-4 sm:px-6">
  <?php if (!$rows): component('empty_state', ['icon' => 'fa-heart', 'title' => 'No favourites yet', 'text' => 'Tap the heart on any ad to save it here and keep an eye on it.', 'cta' => 'Explore listings', 'href' => url('shop.php')]); endif; ?>
  <div class="grid gap-4 md:grid-cols-2">
    <?php foreach ($rows as $l): $active = $l['status'] === 'active'; ?>
      <article class="vk-panel p-3 flex gap-4 <?= $active ? '' : 'opacity-75' ?>" data-row="<?= e($l['uuid']) ?>">
        <a href="<?= url('ad.php', ['id' => $l['uuid']]) ?>" class="relative w-36 sm:w-44 shrink-0 aspect-[4/3] overflow-hidden rounded-2xl bg-surface2">
          <img src="<?= e(media_url($l['cover_thumb'])) ?>" alt="" class="h-full w-full object-cover <?= $active ? '' : 'grayscale' ?>">
          <?php if ($l['is_official']): ?><span class="absolute top-2 left-2 vk-badge-official"><i class="fa-solid fa-crown"></i></span><?php endif; ?>
        </a>
        <div class="flex-1 min-w-0 flex flex-col">
          <div class="flex items-center gap-2"><?= status_pill($active ? 'active' : ($l['status'] === 'sold' ? 'sold' : 'closed')) ?></div>
          <a href="<?= url('ad.php', ['id' => $l['uuid']]) ?>" class="mt-1.5 font-bold leading-snug hover:text-accent line-clamp-2"><?= e($l['title']) ?></a>
          <p class="text-accent font-extrabold"><?= e(money($l['price'], $l['currency_code'], true)) ?> <span class="text-xs text-muted font-semibold"><?= e(price_suffix($l)) ?></span></p>
          <p class="text-xs text-muted truncate"><i class="fa-solid fa-location-dot"></i> <?= e($l['location_name'] . ', ' . $l['region_name']) ?></p>
          <div class="mt-auto pt-2 flex items-center gap-2">
            <span class="text-[11px] text-muted">Saved <?= e(time_ago($l['saved_at'])) ?></span>
            <a href="<?= url('ad.php', ['id' => $l['uuid']]) ?>" class="ml-auto vk-btn-ghost !py-1.5 !px-3 text-xs"><i class="fa-solid fa-arrow-up-right-from-square"></i>Open</a>
            <button type="button" class="vk-btn-ghost !py-1.5 !px-3 text-xs !text-rose-600" data-unfav="<?= e($l['uuid']) ?>"><i class="fa-regular fa-trash-can"></i>Remove</button>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</div>
<?php ob_start(); ?>
<script>
$('[data-unfav]').on('click', function () {
  const id = $(this).data('unfav');
  VK.api('favourites.php', { action: 'remove', listing: id }).then(r => { VK.toast(r.message, 'info'); $('[data-row="' + id + '"]').fadeOut(200, function () { $(this).remove(); }); });
});
$('#clear-inactive').on('click', function () {
  VK.confirm({ title: 'Remove closed ads?', text: 'Ads that are no longer active will be removed from your favourites.', confirmButtonText: 'Remove' })
    .then(r => r && VK.api('favourites.php', { action: 'clear_inactive' }).then(x => { VK.toast(x.message); setTimeout(() => location.reload(), 500); }));
});
</script>
<?php $extraScripts = ob_get_clean(); include APP_ROOT . '/includes/layout/footer.php'; ?>
