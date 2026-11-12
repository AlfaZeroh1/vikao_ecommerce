<?php
/**
 * Listing card. Vars: $l (row from CARD_SELECT), $favs (array flip of fav ids), $layout ('grid'|'list')
 */
$favs = $favs ?? [];
$layout = $layout ?? 'grid';
$isFav = isset($favs[(int)$l['id']]);
$href = url('ad.php', ['id' => $l['uuid']]);
$facts = [];
switch ($l['family']) {
    case 'residential':
    case 'short_stay':
        if ($l['bedrooms'] !== null) $facts[] = ['fa-bed', (int)$l['bedrooms'] === 0 ? 'Studio' : $l['bedrooms'] . ' bd'];
        if ($l['bathrooms'] !== null) $facts[] = ['fa-bath', $l['bathrooms'] . ' ba'];
        if ($l['family'] === 'short_stay' && $l['max_guests']) $facts[] = ['fa-user-group', $l['max_guests'] . ' guests'];
        elseif ($l['res_size']) $facts[] = ['fa-ruler-combined', number_format((float)$l['res_size']) . ' ' . opt_label('size_unit', $l['res_size_unit'])];
        break;
    case 'commercial':
        if ($l['com_size']) $facts[] = ['fa-ruler-combined', number_format((float)$l['com_size']) . ' ' . opt_label('size_unit', $l['com_size_unit'])];
        if ($l['com_parking']) $facts[] = ['fa-square-parking', $l['com_parking'] . ' parking'];
        break;
    case 'land':
        if ($l['land_size']) $facts[] = ['fa-vector-square', rtrim(rtrim(number_format((float)$l['land_size'], 2), '0'), '.') . ' ' . strtolower(opt_label('land_size_unit', $l['land_size_unit']))];
        if ($l['title_status'] === 'title_deed') $facts[] = ['fa-file-contract', 'Title ready'];
        break;
    case 'service':
        if ($l['years_experience']) $facts[] = ['fa-medal', $l['years_experience'] . ' yrs exp'];
        if ($l['emergency_service']) $facts[] = ['fa-truck-medical', '24/7'];
        break;
}
$tierIcon = ['gold' => ['fa-crown', 'text-amber-500'], 'silver' => ['fa-award', 'text-slate-400'], 'bronze' => ['fa-medal', 'text-orange-700']][$l['seller_tier'] ?? ''] ?? null;
?>
<article class="vk-card group <?= $layout === 'list' ? 'sm:flex' : '' ?>" data-listing="<?= (int)$l['id'] ?>">
  <a href="<?= $href ?>" class="relative block overflow-hidden <?= $layout === 'list' ? 'sm:w-72 sm:shrink-0' : '' ?> aspect-[4/3] bg-surface2">
    <img src="<?= e(media_url($l['cover_thumb'] ?? null)) ?>" alt="<?= e($l['title']) ?>" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
    <div class="absolute inset-x-0 top-0 p-2.5 flex flex-wrap gap-1.5">
      <?php if ($l['is_official']): ?><span class="vk-badge-official"><i class="fa-solid fa-crown"></i>Official Vikao</span>
      <?php elseif ($l['is_premium']): ?><span class="vk-badge bg-accent text-accentink"><i class="fa-solid fa-bolt"></i>Premium</span><?php endif; ?>
      <?php if ($l['youtube_video_id']): ?><span class="vk-badge bg-slate-900/75 text-white"><i class="fa-solid fa-vr-cardboard"></i>360°</span><?php endif; ?>
    </div>
    <span class="absolute bottom-2.5 left-2.5 vk-badge bg-slate-900/70 text-white"><i class="fa-solid fa-camera"></i><?= (int)$l['photo_count'] ?></span>
    <span class="absolute bottom-2.5 right-2.5 vk-badge bg-white/90 text-slate-800"><i class="fa-solid <?= e($l['category_icon']) ?>"></i><?= e($l['deal_type'] === 'sale' ? 'For sale' : ($l['deal_type'] === 'rent' ? ($l['family'] === 'land' ? 'Lease' : 'For rent') : ($l['deal_type'] === 'short_stay' ? 'Short stay' : 'Service'))) ?></span>
  </a>
  <button type="button" class="vk-fav-btn absolute top-2.5 right-2.5 <?= $isFav ? 'is-fav' : '' ?>" data-fav="<?= e($l['uuid']) ?>" aria-label="Save to favourites" aria-pressed="<?= $isFav ? 'true' : 'false' ?>">
    <i class="fa-<?= $isFav ? 'solid' : 'regular' ?> fa-heart"></i>
  </button>
  <div class="p-4 flex flex-col gap-2 flex-1 min-w-0">
    <div class="flex items-baseline gap-1.5 flex-wrap">
      <span class="text-lg font-extrabold text-accent tracking-tight"><?= e(money($l['price'], $l['currency_code'], true)) ?></span>
      <span class="text-xs text-muted font-medium"><?= e(price_suffix($l)) ?></span>
      <?php if ($l['is_negotiable']): ?><span class="ml-auto text-[10px] font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">Negotiable</span><?php endif; ?>
    </div>
    <a href="<?= $href ?>" class="font-bold leading-snug line-clamp-2 hover:text-accent transition"><?= e($l['title']) ?></a>
    <p class="text-sm text-muted flex items-center gap-1.5 min-w-0"><i class="fa-solid fa-location-dot text-accent/80"></i><span class="truncate"><?= e(($l['estate'] ? $l['estate'] . ', ' : '') . $l['location_name'] . ($l['region_name'] && $l['region_name'] !== $l['location_name'] ? ', ' . $l['region_name'] : '')) ?></span></p>
    <?php if ($facts): ?>
      <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs font-semibold text-inksoft">
        <?php foreach ($facts as [$i, $t]): ?><span class="inline-flex items-center gap-1.5"><i class="fa-solid <?= $i ?> text-muted"></i><?= e($t) ?></span><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="mt-auto pt-3 border-t border-line/70 flex items-center gap-2 text-xs text-muted">
      <span class="truncate font-semibold text-inksoft"><?= e($l['seller_name']) ?></span>
      <?php if ($l['seller_type'] === 'official'): ?><i class="fa-solid fa-crown text-amber-500" title="Official"></i>
      <?php else: ?>
        <?php if ($l['seller_verified']): ?><i class="fa-solid fa-circle-check text-sky-500" title="Verified"></i><?php endif; ?>
        <?php if ($l['seller_popular']): ?><i class="fa-solid fa-fire text-rose-500" title="Popular seller"></i><?php endif; ?>
        <?php if ($tierIcon): ?><i class="fa-solid <?= $tierIcon[0] ?> <?= $tierIcon[1] ?>" title="<?= e(ucfirst($l['seller_tier'])) ?> member"></i><?php endif; ?>
      <?php endif; ?>
      <span class="ml-auto shrink-0"><?= e(time_ago($l['published_at'] ?? $l['created_at'])) ?></span>
    </div>
  </div>
</article>
