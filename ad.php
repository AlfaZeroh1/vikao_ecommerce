<?php
require __DIR__ . '/includes/bootstrap.php';
$row = find_listing((string)($_GET['id'] ?? ''));
$me = current_user();
$isOwner = $row && $me && (int)$row['user_id'] === (int)$me['id'];
if (!$row || ($row['status'] !== 'active' && !$isOwner && !is_staff() && !in_array($row['status'], ['closed', 'sold', 'expired'], true))) {
    http_response_code(404);
    $pageTitle = 'Ad not found';
    include APP_ROOT . '/includes/layout/header.php';
    echo '<div class="mx-auto max-w-3xl px-4 py-16">'; component('empty_state', ['icon' => 'fa-ghost', 'title' => 'This ad is not available', 'text' => 'It may have been removed or is still under review.', 'cta' => 'Browse listings', 'href' => url('shop.php')]); echo '</div>';
    include APP_ROOT . '/includes/layout/footer.php'; exit;
}
if ($row['status'] === 'active' && !$isOwner) bump_view((int)$row['id']);
$l = load_listing_full($row);
$d = $l['details'];
$seller = $l['seller'];
$favs = favourite_ids(uid());
$isFav = isset($favs[(int)$l['id']]);
$official = (bool)$l['is_official'];
$active = $l['status'] === 'active';
$locNames = array_reverse(array_column($l['location_chain'], 'name'));
$locText = ($l['estate'] ? $l['estate'] . ', ' : '') . implode(', ', $locNames);
$existingConv = $me ? val("SELECT uuid FROM conversations WHERE listing_id = ? AND buyer_id = ?", [$l['id'], $me['id']]) : null;

/* -------- spec grid (icon, label, value) -------- */
$specs = [];
$add = function ($icon, $label, $value) use (&$specs) { if ($value !== null && $value !== '' && $value !== '—') $specs[] = [$icon, $label, $value]; };
$yn = fn($v) => $v === null ? null : ((int)$v ? 'Yes' : 'No');
$add('fa-layer-group', 'Category', $l['category']['name'] ?? '');
switch ($l['family']) {
    case 'residential': case 'short_stay':
        $add($d['property_type_icon'] ?? 'fa-building', 'Property type', $d['property_type_name'] ?? null);
        $add('fa-bed', 'Bedrooms', isset($d['bedrooms']) ? ((int)$d['bedrooms'] === 0 ? 'Studio / bedsitter' : $d['bedrooms']) : null);
        $add('fa-bath', 'Bathrooms', $d['bathrooms'] ?? null);
        $add('fa-toilet', 'Toilets', $d['toilets'] ?? null);
        $add('fa-ruler-combined', 'Size', !empty($d['size_value']) ? number_format((float)$d['size_value']) . ' ' . opt_label('size_unit', $d['size_unit']) : null);
        $add('fa-door-closed', 'Self contained', $yn($d['self_contained'] ?? null));
        $add('fa-wand-sparkles', 'Condition', opt_label('condition', $d['condition'] ?? null));
        $add('fa-couch', 'Furnishing', opt_label('furnishing', $d['furnishing'] ?? null));
        $add('fa-stairs', 'Floor', $d['floor_number'] ?? null);
        $add('fa-square-parking', 'Parking spaces', $d['parking_spaces'] ?? null);
        $add('fa-paw', 'Pets allowed', $yn($d['pets_allowed'] ?? null));
        if ($l['family'] === 'short_stay') {
            $add('fa-user-group', 'Max guests', $d['max_guests'] ?? null);
            $add('fa-bed', 'Beds', $d['beds'] ?? null);
            $add('fa-moon', 'Minimum stay', !empty($d['min_nights']) ? plural((int)$d['min_nights'], 'night') : null);
            $add('fa-right-to-bracket', 'Check-in', !empty($d['check_in_time']) ? date('g:i A', strtotime($d['check_in_time'])) : null);
            $add('fa-right-from-bracket', 'Check-out', !empty($d['check_out_time']) ? date('g:i A', strtotime($d['check_out_time'])) : null);
            $add('fa-broom', 'Cleaning fee', !empty($d['cleaning_fee']) ? money($d['cleaning_fee']) : null);
            $add('fa-shield', 'Security deposit', !empty($d['security_deposit']) ? money($d['security_deposit']) : null);
        }
        break;
    case 'commercial':
        $add($d['property_type_icon'] ?? 'fa-building', 'Property type', $d['property_type_name'] ?? null);
        $add('fa-ruler-combined', 'Size', !empty($d['size_value']) ? number_format((float)$d['size_value']) . ' ' . opt_label('size_unit', $d['size_unit']) : null);
        $add('fa-square-parking', 'Parking spaces', $d['parking_spaces'] ?? null);
        $add('fa-wand-sparkles', 'Condition', opt_label('condition', $d['condition'] ?? null));
        $add('fa-couch', 'Furnishing', opt_label('furnishing', $d['furnishing'] ?? null));
        $add('fa-stairs', 'Floor', isset($d['floor_number']) ? $d['floor_number'] . (!empty($d['total_floors']) ? ' of ' . $d['total_floors'] : '') : null);
        $add('fa-people-group', 'Capacity', !empty($d['capacity_people']) ? number_format((int)$d['capacity_people']) . ' people' : null);
        $add('fa-restroom', 'Washrooms', $d['washrooms'] ?? null);
        $add('fa-bolt', 'Power', opt_label('power_phase', $d['power_phase'] ?? null));
        $add('fa-map', 'Zoning', opt_label('commercial_zoning', $d['zoning'] ?? null));
        $add('fa-tag', 'Price is', opt_label('price_unit_commercial', $l['price_unit']));
        break;
    case 'land':
        $add($d['property_type_icon'] ?? 'fa-map', 'Land type', $d['property_type_name'] ?? null);
        $add('fa-vector-square', 'Size', isset($d['size_value']) ? rtrim(rtrim(number_format((float)$d['size_value'], 4), '0'), '.') . ' ' . opt_label('land_size_unit', $d['size_unit']) . ($d['size_unit'] !== 'acre' && $d['size_acres'] ? ' (≈ ' . rtrim(rtrim(number_format((float)$d['size_acres'], 3), '0'), '.') . ' acres)' : '') : null);
        $add('fa-scale-balanced', 'Tenure', opt_label('tenure', $d['tenure'] ?? null) . (!empty($d['lease_years_remaining']) ? ' · ' . $d['lease_years_remaining'] . ' yrs left' : ''));
        $add('fa-file-contract', 'Document', opt_label('title_status', $d['title_status'] ?? null));
        $add('fa-city', 'Zoning', opt_label('zoning', $d['zoning'] ?? null));
        $add('fa-mountain', 'Topography', opt_label('topography', $d['topography'] ?? null));
        $add('fa-seedling', 'Soil', opt_label('soil_type', $d['soil_type'] ?? null));
        $add('fa-road', 'Road access', opt_label('road_access', $d['road_access'] ?? null));
        $add('fa-route', 'To tarmac', !empty($d['distance_to_tarmac_km']) ? (float)$d['distance_to_tarmac_km'] . ' km' : null);
        $add('fa-border-top-left', 'Fenced', $yn($d['is_fenced'] ?? null));
        $add('fa-faucet', 'Serviced', $yn($d['is_serviced'] ?? null));
        $add('fa-object-ungroup', 'Subdivided', $yn($d['is_subdivided'] ?? null));
        $add('fa-arrows-turn-right', 'Corner plot', $yn($d['is_corner_plot'] ?? null));
        $add('fa-calendar-check', 'Payment plan', !empty($d['payment_plan']) ? 'Available' : null);
        break;
    case 'service':
        $add('fa-id-badge', 'Provider', opt_label('provider_type', $d['provider_type'] ?? null) . (!empty($d['business_name']) ? ' · ' . $d['business_name'] : ''));
        $add('fa-tag', 'Pricing', opt_label('pricing_model', $d['pricing_model'] ?? null));
        $add('fa-medal', 'Experience', !empty($d['years_experience']) ? plural((int)$d['years_experience'], 'year') : null);
        $add('fa-people-group', 'Team size', opt_label('team_size', $d['team_size'] ?? null));
        $add('fa-calendar-days', 'Availability', opt_label('availability', $d['availability'] ?? null));
        $add('fa-stopwatch', 'Response time', opt_label('response_time', $d['response_time'] ?? null));
        $add('fa-truck-medical', 'Emergency service', !empty($d['emergency_service']) ? '24/7 available' : null);
        $add('fa-cubes', 'Materials', !empty($d['provides_materials']) ? 'Provided' : null);
        $add('fa-clipboard-check', 'Site visit', !empty($d['free_site_visit']) ? 'Free' : null);
        $add('fa-shield-halved', 'Insured', !empty($d['is_insured']) ? 'Yes' : null);
        $add('fa-award', 'Warranty', !empty($d['warranty_months']) ? $d['warranty_months'] . ' months' : null);
        $add('fa-certificate', 'Licence', !empty($d['license_body']) ? $d['license_body'] . (!empty($d['license_number']) ? ' · ' . $d['license_number'] : '') : null);
        $add('fa-language', 'Languages', $d['languages'] ?? null);
        break;
}
$add('fa-user-tie', 'Listed by', opt_label('listed_by', $l['listed_by']));
$add('fa-hashtag', 'Ad reference', $l['ref_code']);

$featuresByGroup = [];
foreach ($l['features'] as $ft) $featuresByGroup[$ft['feature_group']][] = $ft;

$similar = listing_cards("l.status = 'active' AND l.id <> :id AND l.category_id = :cat", ['id' => $l['id'], 'cat' => $l['category_id']],
    'CASE WHEN l.region_id = ' . (int)$l['region_id'] . ' THEN 0 ELSE 1 END, l.published_at DESC', 4);

$pageTitle = $l['title'];
$pageDescription = excerpt($l['description_text'], 155);
$extraHead = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.css">'
    . '<meta property="og:title" content="' . e($l['title']) . '"><meta property="og:image" content="' . e(media_url($l['media'][0]['path'] ?? null)) . '">';
include APP_ROOT . '/includes/layout/header.php';
?>
<div class="mx-auto max-w-7xl px-4 sm:px-6 pt-6">
  <?php if (!$active): ?>
    <div class="mb-4 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm flex items-center gap-3">
      <i class="fa-solid fa-circle-info text-amber-600"></i>
      <span>This ad is <b><?= e(STATUS_META[$l['status']][0] ?? $l['status']) ?></b><?= $l['status'] === 'pending' ? ' — only you and Vikao reviewers can see it.' : '.' ?></span>
      <?php if ($isOwner): ?><a href="<?= url('my-listings.php') ?>" class="ml-auto font-bold text-accent">Manage</a><?php endif; ?>
    </div>
  <?php endif; ?>
  <nav class="text-xs text-muted flex flex-wrap items-center gap-2 mb-4" aria-label="Breadcrumb">
    <a href="<?= url() ?>" class="hover:text-accent"><i class="fa-solid fa-house"></i></a><i class="fa-solid fa-chevron-right text-[9px]"></i>
    <a href="<?= url('shop.php', ['cat' => $l['category']['slug']]) ?>" class="hover:text-accent"><?= e($l['category']['name']) ?></a><i class="fa-solid fa-chevron-right text-[9px]"></i>
    <a href="<?= url('shop.php', ['cat' => $l['category']['slug'], 'county' => $l['region_id']]) ?>" class="hover:text-accent"><?= e(end($locNames)) ?></a><i class="fa-solid fa-chevron-right text-[9px]"></i>
    <span class="text-ink font-semibold"><?= e($l['ref_code']) ?></span>
  </nav>
</div>

<div class="mx-auto max-w-7xl px-4 sm:px-6 grid lg:grid-cols-[1fr_380px] gap-6 items-start">
  <!-- ================= LEFT ================= -->
  <div class="space-y-6 min-w-0">
    <!-- Gallery -->
    <div class="vk-panel overflow-hidden">
      <div class="relative">
        <div class="swiper vk-gallery aspect-[16/10] bg-surface2">
          <div class="swiper-wrapper">
            <?php foreach ($l['media'] as $i => $m): ?>
              <div class="swiper-slide"><img src="<?= e(media_url($m['path'])) ?>" alt="<?= e($l['title']) ?> photo <?= $i + 1 ?>" class="h-full w-full object-cover" <?= $i ? 'loading="lazy"' : '' ?>></div>
            <?php endforeach; ?>
          </div>
          <div class="swiper-button-prev"></div><div class="swiper-button-next"></div>
        </div>
        <div class="absolute top-3 left-3 z-10 flex flex-wrap gap-2">
          <?php if ($official): ?><span class="vk-badge-official !text-xs !px-2.5 !py-1.5"><i class="fa-solid fa-crown"></i>Official Vikao</span><?php endif; ?>
          <?php if ($l['is_premium'] && !$official): ?><span class="vk-badge bg-accent text-accentink !text-xs"><i class="fa-solid fa-bolt"></i>Premium</span><?php endif; ?>
          <?php if ($l['youtube_video_id']): ?><a href="#tour" class="vk-badge bg-slate-900/80 text-white !text-xs"><i class="fa-solid fa-vr-cardboard"></i>360° tour</a><?php endif; ?>
        </div>
        <div class="absolute top-3 right-3 z-10 flex gap-2">
          <button type="button" class="vk-fav-btn <?= $isFav ? 'is-fav' : '' ?>" data-fav="<?= e($l['uuid']) ?>" aria-label="Save"><i class="fa-<?= $isFav ? 'solid' : 'regular' ?> fa-heart"></i></button>
          <button type="button" class="vk-fav-btn" data-share aria-label="Share"><i class="fa-solid fa-share-nodes"></i></button>
        </div>
        <span class="absolute bottom-3 right-3 z-10 vk-badge bg-slate-900/75 text-white !text-xs"><i class="fa-solid fa-images"></i><span id="g-idx">1</span> / <?= count($l['media']) ?></span>
      </div>
      <?php if (count($l['media']) > 1): ?>
      <div class="swiper vk-thumbs p-2">
        <div class="swiper-wrapper">
          <?php foreach ($l['media'] as $m): ?><div class="swiper-slide !w-24 aspect-[4/3] rounded-xl overflow-hidden"><img src="<?= e(media_url($m['thumb_path'])) ?>" alt="" class="h-full w-full object-cover" loading="lazy"></div><?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Title block -->
    <div class="vk-panel p-5 sm:p-7">
      <div class="flex flex-wrap items-center gap-2 text-xs font-bold">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-accent/10 px-2.5 py-1 text-accent"><i class="fa-solid <?= e($l['category']['icon']) ?>"></i><?= e($l['category']['name']) ?></span>
        <?php if ($l['is_negotiable']): ?><span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-1 text-emerald-600 dark:text-emerald-400"><i class="fa-solid fa-handshake"></i>Negotiable</span><?php endif; ?>
        <span class="ml-auto inline-flex items-center gap-3 text-muted font-semibold"><span><i class="fa-regular fa-eye"></i> <?= number_format((int)$l['views_count']) ?></span><span><i class="fa-regular fa-clock"></i> <?= e(time_ago($l['published_at'] ?? $l['created_at'])) ?></span></span>
      </div>
      <h1 class="mt-3 font-display text-2xl sm:text-3xl font-bold leading-tight tracking-tight"><?= e($l['title']) ?></h1>
      <p class="mt-2 text-muted flex items-start gap-2"><i class="fa-solid fa-location-dot text-accent mt-1"></i><span><?= e($locText) ?><?= $l['address'] ? ' · ' . e($l['address']) : '' ?></span></p>
      <!-- quick facts -->
      <div class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-3">
        <?php foreach (array_slice(array_values(array_filter($specs, fn($s) => !in_array($s[1], ['Category', 'Ad reference', 'Listed by'], true))), 0, 4) as [$i, $lab, $v]): ?>
          <div class="rounded-2xl bg-surface2 px-4 py-3"><i class="fa-solid <?= e($i) ?> text-accent"></i><p class="mt-1 text-[11px] font-bold uppercase tracking-wide text-muted"><?= e($lab) ?></p><p class="font-bold truncate"><?= e($v) ?></p></div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Details -->
    <div class="vk-panel p-5 sm:p-7">
      <h2 class="vk-section-title mb-4"><i class="fa-solid fa-list-check"></i>Details</h2>
      <dl class="grid sm:grid-cols-2 gap-x-8">
        <?php foreach ($specs as [$i, $lab, $v]): ?>
          <div class="flex items-center gap-3 py-3 border-b border-line/70">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-surface2 text-muted"><i class="fa-solid <?= e($i) ?>"></i></span>
            <dt class="text-sm text-muted"><?= e($lab) ?></dt><dd class="ml-auto text-sm font-bold text-right"><?= e($v) ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>
    </div>

    <?php if ($l['features']): ?>
    <div class="vk-panel p-5 sm:p-7">
      <h2 class="vk-section-title mb-4"><i class="fa-solid fa-star"></i><?= $l['family'] === 'land' ? 'Highlights' : 'Features & amenities' ?> <span class="text-sm text-muted font-semibold">(<?= count($l['features']) ?>)</span></h2>
      <div class="space-y-4">
        <?php foreach ($featuresByGroup as $g => $fs): ?>
          <div><p class="text-xs font-bold uppercase tracking-wider text-muted mb-2"><?= e($g) ?></p>
            <div class="flex flex-wrap gap-2">
              <?php foreach ($fs as $ft): ?><span class="inline-flex items-center gap-2 rounded-xl border border-line bg-surface px-3 py-2 text-sm font-semibold"><i class="fa-solid <?= e($ft['icon']) ?> text-accent"></i><?= e($ft['name']) ?></span><?php endforeach; ?>
            </div></div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($l['family'] === 'service' && $l['service_areas']): ?>
    <div class="vk-panel p-5 sm:p-7">
      <h2 class="vk-section-title mb-4"><i class="fa-solid fa-map-location-dot"></i>Areas served</h2>
      <div class="flex flex-wrap gap-2"><?php foreach ($l['service_areas'] as $a): ?><span class="vk-chip"><i class="fa-solid fa-location-dot"></i><?= e($a['name']) ?></span><?php endforeach; ?></div>
    </div>
    <?php endif; ?>

    <div class="vk-panel p-5 sm:p-7">
      <h2 class="vk-section-title mb-4"><i class="fa-solid fa-align-left"></i>Description</h2>
      <div class="vk-prose relative max-h-[28rem] overflow-hidden" id="desc"><?= $l['description_html'] ?></div>
      <button type="button" class="mt-3 text-sm font-bold text-accent hidden" id="desc-more">Read more <i class="fa-solid fa-chevron-down"></i></button>
    </div>

    <?php if ($l['youtube_video_id']): ?>
    <div class="vk-panel p-5 sm:p-7" id="tour">
      <h2 class="vk-section-title mb-4"><i class="fa-solid fa-vr-cardboard"></i>360° virtual tour</h2>
      <div class="aspect-video overflow-hidden rounded-2xl bg-black">
        <iframe class="h-full w-full" src="https://www.youtube-nocookie.com/embed/<?= e($l['youtube_video_id']) ?>?rel=0" title="360° tour" loading="lazy" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
      </div>
      <p class="mt-2 text-xs text-muted"><i class="fa-solid fa-hand-pointer"></i> Drag inside the video (or move your phone) to look around.</p>
    </div>
    <?php endif; ?>

    <div class="vk-panel p-5 flex flex-wrap items-center gap-3 text-sm">
      <i class="fa-solid fa-shield-halved text-emerald-500 text-lg"></i>
      <p class="flex-1 min-w-[200px] text-muted"><b class="text-ink">Safety tips:</b> view the property in person, verify ownership documents at the Lands registry, and never send money before viewing.</p>
      <button type="button" class="vk-btn-ghost !py-2" id="report-btn"><i class="fa-regular fa-flag"></i>Report ad</button>
    </div>
  </div>

  <!-- ================= RIGHT (sticky action column) ================= -->
  <aside class="space-y-4 lg:sticky lg:top-32">
    <div class="vk-panel p-5 sm:p-6 shadow-soft">
      <p class="text-xs font-bold uppercase tracking-wider text-muted"><?= $l['deal_type'] === 'sale' ? 'Price' : ($l['deal_type'] === 'service' ? 'Starting from' : 'Rent') ?></p>
      <p class="mt-1 flex items-baseline gap-2 flex-wrap"><span class="font-display text-3xl font-bold text-accent"><?= e(money($l['price'], $l['currency_code'])) ?></span><span class="text-muted font-semibold"><?= e(price_suffix($l)) ?></span></p>
      <?php if ($l['has_service_charge']): ?>
        <p class="mt-2 text-sm text-muted"><i class="fa-solid fa-receipt mr-1"></i>Service charge <b class="text-ink"><?= e(money($l['service_charge_amount'])) ?></b> <?= e(opt_label('service_charge_period', $l['service_charge_period'])) ?><?php if ($l['service_charge_notes']): ?><br><span class="text-xs"><?= e($l['service_charge_notes']) ?></span><?php endif; ?></p>
      <?php endif; ?>

      <?php if ($official): ?>
        <!-- OFFICIAL PIPELINE -->
        <div class="mt-5 rounded-2xl bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-500/10 dark:to-orange-500/5 ring-1 ring-amber-500/25 p-4">
          <div class="flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-xl bg-amber-500 text-slate-900 text-lg"><i class="fa-solid fa-ticket"></i></span>
            <div><p class="text-xs font-bold uppercase tracking-wide text-amber-700 dark:text-amber-300">Reservation fee</p><p class="text-2xl font-extrabold"><?= e(money($l['reservation_fee'], $l['currency_code'])) ?></p></div>
          </div>
          <p class="mt-2 text-xs text-inksoft">Secures this property for you and is deducted from the final amount. Fully refundable within 7 days if Vikao cannot complete the deal.</p>
        </div>
        <?php if ($l['packages']): ?>
          <p class="mt-4 text-xs font-bold uppercase tracking-wider text-muted">Available packages</p>
          <ul class="mt-2 space-y-1.5 text-sm">
            <?php foreach ($l['packages'] as $p): ?><li class="flex items-center gap-2"><i class="fa-solid fa-box-open text-accent"></i><span class="flex-1"><?= e($p['name']) ?></span><b><?= e(money($p['total_price'], $l['currency_code'], true)) ?></b></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if ($active): ?>
          <a href="<?= url('reserve.php', ['id' => $l['uuid']]) ?>" class="vk-btn-accent w-full mt-5 !py-4 text-base"><i class="fa-solid fa-bolt"></i>Reserve now</a>
        <?php endif; ?>
        <p class="mt-3 text-center text-xs text-muted"><i class="fa-solid fa-lock"></i> Secure checkout · M-Pesa</p>
      <?php else: ?>
        <!-- NORMAL PIPELINE -->
        <?php if ($isOwner): ?>
          <a href="<?= url('sell.php', ['edit' => $l['uuid']]) ?>" class="vk-btn-dark w-full mt-5"><i class="fa-solid fa-pen"></i>Edit ad</a>
          <a href="<?= url('my-listings.php') ?>" class="vk-btn-ghost w-full mt-2"><i class="fa-solid fa-rectangle-list"></i>My listings</a>
        <?php elseif ($active): ?>
          <button type="button" class="vk-btn-accent w-full mt-5 !py-3.5" id="chat-btn"><i class="fa-solid fa-comments"></i><?= $existingConv ? 'Continue chat' : 'Start chat with seller' ?></button>
          <button type="button" class="vk-btn-ghost w-full mt-2 !py-3.5" id="phone-btn"><i class="fa-solid fa-phone"></i><span>Show contact · <?= e(mask_phone($seller['phone'])) ?></span></button>
          <div class="mt-3 flex flex-wrap gap-1.5" id="quick-msgs">
            <?php foreach (['Is this still available?', 'Can I book a viewing?', 'Is the price negotiable?', 'What are the payment terms?'] as $qm): ?><button type="button" class="vk-chip !text-xs" data-quick="<?= e($qm) ?>"><?= e($qm) ?></button><?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- seller card -->
    <div class="vk-panel p-5">
      <div class="flex items-center gap-3">
        <?php if ($seller['avatar_url']): ?><img src="<?= e($seller['avatar_url']) ?>" class="h-14 w-14 rounded-2xl object-cover" alt="">
        <?php else: ?><span class="grid h-14 w-14 place-items-center rounded-2xl <?= $official ? 'bg-nav' : 'bg-gradient-to-br from-slate-600 to-slate-800' ?> text-white font-bold text-lg"><?= $official ? '<img src="' . asset('img/logo-mark.svg') . '" class="h-14 w-14 rounded-2xl" alt="">' : e(initials($seller['display_name'])) ?></span><?php endif; ?>
        <div class="min-w-0">
          <p class="font-bold truncate"><?= e($seller['display_name']) ?></p>
          <p class="text-xs text-muted">Member since <?= e(fdate($seller['created_at'], 'M Y')) ?> · <?= plural((int)$seller['active_ads'], 'active ad') ?></p>
        </div>
      </div>
      <div class="mt-3"><?php component('seller_badges', ['user' => $seller, 'badges' => $l['seller_badges'], 'size' => 'md']); ?></div>
      <?php if ($seller['last_seen_at']): ?><p class="mt-3 text-xs text-muted"><span class="inline-block h-2 w-2 rounded-full <?= strtotime($seller['last_seen_at']) > time() - 300 ? 'bg-emerald-500' : 'bg-slate-400' ?> mr-1"></span>Last seen <?= e(time_ago($seller['last_seen_at'])) ?></p><?php endif; ?>
    </div>
  </aside>
</div>

<?php if ($similar): ?>
<section class="mx-auto max-w-7xl px-4 sm:px-6 mt-12">
  <h2 class="font-display text-2xl font-bold mb-5">Similar listings</h2>
  <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4"><?php foreach ($similar as $s) component('ad_card', ['l' => $s, 'favs' => $favs]); ?></div>
</section>
<?php endif; ?>

<?php ob_start(); ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Swiper/11.0.5/swiper-bundle.min.js"></script>
<script>
(function () {
  const AD = <?= json_encode(['uuid' => $l['uuid'], 'conv' => $existingConv, 'loggedIn' => (bool)$me]) ?>;
  const thumbs = document.querySelector('.vk-thumbs') ? new Swiper('.vk-thumbs', { slidesPerView: 'auto', spaceBetween: 8, freeMode: true, watchSlidesProgress: true }) : null;
  new Swiper('.vk-gallery', { loop: <?= count($l['media']) > 1 ? 'true' : 'false' ?>, keyboard: true, navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
    thumbs: thumbs ? { swiper: thumbs } : undefined, on: { slideChange: s => $('#g-idx').text(s.realIndex + 1) } });

  const $d = $('#desc'); if ($d[0] && $d[0].scrollHeight > $d.outerHeight() + 10) { $d.addClass('[mask-image:linear-gradient(black_70%,transparent)]'); $('#desc-more').removeClass('hidden'); }
  $('#desc-more').on('click', function () { $d.removeClass('max-h-[28rem] [mask-image:linear-gradient(black_70%,transparent)]'); $(this).remove(); });

  function startChat(message) {
    if (AD.conv && !message) { location.href = VK.url('chats.php', { c: AD.conv }); return; }
    VK.api('chats.php', { action: 'start', listing: AD.uuid, message: message || '' }).then(r => location.href = r.redirect);
  }
  $('#chat-btn').on('click', () => startChat());
  $('[data-quick]').on('click', function () { startChat($(this).data('quick')); });
  $('#phone-btn').on('click', function () {
    const $b = $(this);
    VK.api('listings.php', { action: 'reveal_phone', listing: AD.uuid }).then(r => {
      $b.replaceWith(`<a href="tel:${r.phone}" class="vk-btn-ghost w-full mt-2 !py-3.5 !border-emerald-500 !text-emerald-600 dark:!text-emerald-400"><i class="fa-solid fa-phone-volume"></i>${r.pretty}</a>
        <a href="https://wa.me/${r.phone.replace('+', '')}?text=${encodeURIComponent('Hi, I saw your ad on Vikao: ' + location.href)}" target="_blank" rel="noopener" class="vk-btn-ghost w-full mt-2"><i class="fa-brands fa-whatsapp text-emerald-500"></i>WhatsApp</a>`);
    });
  });
  $('#report-btn').on('click', function () {
    Swal.fire({ title: 'Report this ad', input: 'select', inputOptions: { scam: 'Scam / fraud', wrong_category: 'Wrong category', sold: 'Already sold / let', duplicate: 'Duplicate ad', offensive: 'Offensive content', wrong_price: 'Wrong price', other: 'Other' },
      inputPlaceholder: 'Choose a reason', showCancelButton: true, confirmButtonText: 'Report', inputValidator: v => !v && 'Choose a reason' })
      .then(r => { if (r.isConfirmed) VK.api('listings.php', { action: 'report', listing: AD.uuid, reason: r.value }).then(x => VK.toast(x.message)); });
  });
})();
</script>
<?php $extraScripts = ob_get_clean(); include APP_ROOT . '/includes/layout/footer.php'; ?>
