<?php
require __DIR__ . '/includes/bootstrap.php';
$f = shop_filters();
$res = search_listings($f);
$family = $res['family'];
$favs = favourite_ids(uid());
$cat = $f['cat'] ? category_by_slug($f['cat']) : null;
$counties = all("SELECT id, name FROM locations WHERE country_id = 1 AND level = 1 ORDER BY name");

$heading = $cat ? $cat['name'] : ($f['official'] ? 'Official Vikao Store' : ($f['q'] !== '' ? 'Results for “' . $f['q'] . '”' : 'All listings'));
$headIcon = $cat ? $cat['icon'] : ($f['official'] ? 'fa-crown' : 'fa-grip');

/* helpers for checked state */
$has = fn(string $k, $v) => in_array((string)$v, array_map('strval', (array)$f[$k]), true);
$chk = fn(string $k, $v) => $has($k, $v) ? 'checked' : '';
$fam = fn(...$fs) => 'data-families="' . implode(' ', $fs) . '"';

$pageTitle = $heading;
include APP_ROOT . '/includes/layout/header.php';
?>
<div class="mx-auto max-w-7xl px-4 sm:px-6 pt-6">
  <!-- breadcrumb + title -->
  <nav class="text-xs text-muted flex items-center gap-2 mb-3" aria-label="Breadcrumb">
    <a href="<?= url() ?>" class="hover:text-accent"><i class="fa-solid fa-house"></i></a><i class="fa-solid fa-chevron-right text-[9px]"></i>
    <a href="<?= url('shop.php') ?>" class="hover:text-accent">Shop</a>
    <?php if ($cat && $cat['parent_id']): $parent = categories()[(int)$cat['parent_id']]; ?>
      <i class="fa-solid fa-chevron-right text-[9px]"></i><a href="<?= url('shop.php', ['cat' => $parent['slug']]) ?>" class="hover:text-accent"><?= e($parent['name']) ?></a>
    <?php endif; ?>
    <?php if ($cat): ?><i class="fa-solid fa-chevron-right text-[9px]"></i><span class="text-ink font-semibold"><?= e($cat['name']) ?></span><?php endif; ?>
  </nav>
  <div class="flex flex-wrap items-center gap-3">
    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-nav text-amber-400 text-lg"><i class="fa-solid <?= e($headIcon) ?>" id="head-icon"></i></span>
    <div class="min-w-0">
      <h1 class="font-display text-2xl sm:text-3xl font-bold tracking-tight truncate" id="head-title"><?= e($heading) ?></h1>
      <p class="text-sm text-muted"><span id="result-count" class="font-bold text-ink"><?= number_format($res['total']) ?></span> ads found</p>
    </div>
    <div class="ml-auto flex items-center gap-2">
      <button type="button" class="vk-btn-ghost lg:hidden" data-filters-open><i class="fa-solid fa-sliders"></i>Filters <span class="hidden rounded-full bg-accent px-1.5 text-[10px] text-accentink" id="filter-count-m"></span></button>
      <div class="vk-input-group">
        <i class="fa-solid fa-arrow-down-wide-short"></i>
        <select form="filters" name="sort" class="vk-select !py-2.5 w-40 sm:w-52" data-filter>
          <?php foreach (OPT['sort'] as $k => $v): ?><option value="<?= $k ?>" <?= $f['sort'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="hidden sm:flex rounded-xl border border-line p-1 bg-surface">
        <button type="button" class="vk-icon-btn !h-9 !w-9 is-active" data-layout="grid" aria-label="Grid view"><i class="fa-solid fa-grip"></i></button>
        <button type="button" class="vk-icon-btn !h-9 !w-9" data-layout="list" aria-label="List view"><i class="fa-solid fa-list"></i></button>
      </div>
    </div>
  </div>
  <!-- active filter chips -->
  <div id="active-chips" class="mt-4 flex flex-wrap gap-2"></div>
</div>

<div class="mx-auto max-w-7xl px-4 sm:px-6 mt-5 grid lg:grid-cols-[300px_1fr] gap-6 items-start">
  <!-- ============== FILTER SIDEBAR ============== -->
  <aside id="filter-drawer" class="fixed inset-0 z-50 hidden lg:block lg:static lg:z-auto">
    <div class="absolute inset-0 bg-slate-950/50 lg:hidden" data-filters-close></div>
    <form id="filters" action="<?= url('shop.php') ?>" class="absolute inset-y-0 left-0 w-[88%] max-w-sm overflow-y-auto bg-canvas p-4 lg:p-0 lg:static lg:w-auto lg:max-w-none lg:overflow-visible lg:bg-transparent lg:sticky lg:top-32 space-y-3" autocomplete="off">
      <input type="hidden" name="page" value="<?= (int)$res['page'] ?>">
      <div class="flex items-center justify-between lg:hidden mb-2"><h2 class="font-display text-xl font-bold">Filters</h2><button type="button" class="vk-icon-btn" data-filters-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button></div>

      <div class="vk-panel p-4 space-y-3">
        <div class="vk-input-group"><i class="fa-solid fa-magnifying-glass"></i><input name="q" value="<?= e($f['q']) ?>" class="vk-input" placeholder="Keyword, estate, ref…" data-filter data-debounce></div>
        <div>
          <label class="vk-label"><i class="fa-solid fa-layer-group text-accent mr-1"></i>Category</label>
          <select name="cat" class="vk-select" data-filter id="f-cat">
            <option value="" data-family="">All categories</option>
            <?php foreach (root_categories() as $r): ?>
              <optgroup label="<?= e($r['name']) ?>">
                <option value="<?= e($r['slug']) ?>" data-family="<?= e($r['family']) ?>" data-deal="<?= e($r['deal_type'] ?? '') ?>" <?= $f['cat'] === $r['slug'] ? 'selected' : '' ?>>All <?= e(strtolower($r['name'])) ?></option>
                <?php foreach (category_children((int)$r['id']) as $ch): ?>
                  <option value="<?= e($ch['slug']) ?>" data-family="<?= e($ch['family']) ?>" data-deal="<?= e($ch['deal_type']) ?>" <?= $f['cat'] === $ch['slug'] ? 'selected' : '' ?>><?= e($r['family'] === 'service' ? $ch['name'] : ($ch['deal_type'] === 'sale' ? $r['name'] . ' · For sale' : $r['name'] . ($r['family'] === 'land' ? ' · For lease' : ' · For rent'))) ?></option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <?php component('filter_section', ['title' => 'Location', 'icon' => 'fa-location-dot', 'open' => true, 'body' => function () use ($f, $counties) { ?>
        <select name="county" class="vk-select" data-filter data-loc-level="1" data-loc-target="#f-sub">
          <option value="">All counties</option>
          <?php foreach ($counties as $c): ?><option value="<?= $c['id'] ?>" <?= $f['county'] == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="subcounty" id="f-sub" class="vk-select" data-filter data-loc-level="2" data-loc-target="#f-area" data-placeholder="All sub-counties" data-selected="<?= e($f['subcounty']) ?>" disabled><option value="">All sub-counties</option></select>
        <select name="area" id="f-area" class="vk-select" data-filter data-loc-level="3" data-placeholder="All areas" data-selected="<?= e($f['area']) ?>" disabled><option value="">All areas</option></select>
      <?php }]); ?>

      <?php component('filter_section', ['title' => 'Price (KSh)', 'icon' => 'fa-tags', 'open' => true, 'body' => function () use ($f) { ?>
        <div class="grid grid-cols-2 gap-2">
          <input name="min_price" value="<?= e($f['min_price']) ?>" inputmode="numeric" class="vk-input" placeholder="Min" data-filter data-debounce>
          <input name="max_price" value="<?= e($f['max_price']) ?>" inputmode="numeric" class="vk-input" placeholder="Max" data-filter data-debounce>
        </div>
        <div class="flex flex-wrap gap-1.5" id="price-presets"></div>
      <?php }]); ?>

      <?php component('filter_section', ['title' => 'Billing period', 'icon' => 'fa-calendar-days', 'families' => 'residential commercial land short_stay', 'deal' => 'rent short_stay', 'body' => function () use ($chk) { ?>
        <div class="flex flex-wrap gap-1.5">
          <?php foreach (OPT['billing_period'] as $k => $v): ?><label class="vk-choice"><input type="checkbox" name="billing[]" value="<?= $k ?>" <?= $chk('billing', $k) ?> data-filter><span><?= e(ucfirst(str_replace('per ', '', $v))) ?></span></label><?php endforeach; ?>
        </div>
      <?php }]); ?>

      <?php foreach (['residential' => 'residential short_stay', 'commercial' => 'commercial', 'land' => 'land'] as $pf => $fams): ?>
        <?php component('filter_section', ['title' => $pf === 'land' ? 'Land type' : 'Property type', 'icon' => 'fa-building', 'families' => $fams, 'open' => true, 'body' => function () use ($pf, $chk) { $types = property_types($pf); ?>
          <div class="grid grid-cols-1 gap-1 <?= count($types) > 10 ? 'max-h-64 overflow-y-auto pr-1' : '' ?>">
            <?php foreach ($types as $t): ?>
              <label class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-surface2 cursor-pointer text-sm"><input type="checkbox" name="ptype[]" value="<?= $t['id'] ?>" <?= $chk('ptype', $t['id']) ?> class="h-4 w-4 accent-amber-600" data-filter><i class="fa-solid <?= e($t['icon']) ?> w-4 text-muted"></i><?= e($t['name']) ?></label>
            <?php endforeach; ?>
          </div>
        <?php }]); ?>
      <?php endforeach; ?>

      <?php component('filter_section', ['title' => 'Bedrooms & bathrooms', 'icon' => 'fa-bed', 'families' => 'residential short_stay', 'open' => true, 'body' => function () use ($f, $chk) { ?>
        <div class="flex flex-wrap gap-1.5">
          <?php foreach (['0' => 'Studio', '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5+' => '5+'] as $k => $v): ?><label class="vk-choice"><input type="checkbox" name="beds[]" value="<?= $k ?>" <?= $chk('beds', $k) ?> data-filter><span><?= $v ?></span></label><?php endforeach; ?>
        </div>
        <label class="vk-label !mt-3">Bathrooms</label>
        <div class="flex flex-wrap gap-1.5">
          <?php foreach (['' => 'Any', '1' => '1+', '2' => '2+', '3' => '3+', '4' => '4+'] as $k => $v): ?><label class="vk-choice"><input type="radio" name="baths" value="<?= $k ?>" <?= (string)$f['baths'] === (string)$k ? 'checked' : '' ?> data-filter><span><i class="fa-solid fa-bath"></i><?= $v ?></span></label><?php endforeach; ?>
        </div>
      <?php }]); ?>

      <?php component('filter_section', ['title' => 'Guests', 'icon' => 'fa-user-group', 'families' => 'short_stay', 'open' => true, 'body' => function () use ($f) { ?>
        <div class="flex flex-wrap gap-1.5">
          <?php foreach (['' => 'Any', '2' => '2+', '4' => '4+', '6' => '6+', '8' => '8+', '12' => '12+'] as $k => $v): ?><label class="vk-choice"><input type="radio" name="guests" value="<?= $k ?>" <?= (string)$f['guests'] === (string)$k ? 'checked' : '' ?> data-filter><span><?= $v ?></span></label><?php endforeach; ?>
        </div>
      <?php }]); ?>

      <?php component('filter_section', ['title' => 'Size (m²)', 'icon' => 'fa-ruler-combined', 'families' => 'residential commercial', 'body' => function () use ($f) { ?>
        <div class="grid grid-cols-2 gap-2"><input name="min_size" value="<?= e($f['min_size']) ?>" class="vk-input" placeholder="Min m²" inputmode="numeric" data-filter data-debounce><input name="max_size" value="<?= e($f['max_size']) ?>" class="vk-input" placeholder="Max m²" inputmode="numeric" data-filter data-debounce></div>
        <p class="text-[11px] text-muted">1 m² ≈ 10.76 ft² — sizes in ft² are converted automatically.</p>
      <?php }]); ?>

      <?php component('filter_section', ['title' => 'Land size (acres)', 'icon' => 'fa-vector-square', 'families' => 'land', 'open' => true, 'body' => function () use ($f) { ?>
        <div class="grid grid-cols-2 gap-2"><input name="min_acres" value="<?= e($f['min_acres']) ?>" class="vk-input" placeholder="Min" inputmode="decimal" data-filter data-debounce><input name="max_acres" value="<?= e($f['max_acres']) ?>" class="vk-input" placeholder="Max" inputmode="decimal" data-filter data-debounce></div>
        <div class="flex flex-wrap gap-1.5">
          <?php foreach ([['0', '0.13', '≤ ⅛ acre'], ['0.24', '0.26', '¼ acre'], ['0.49', '0.51', '½ acre'], ['1', '5', '1–5 acres'], ['5', '', '5+ acres']] as [$a, $b, $t]): ?>
            <button type="button" class="vk-chip" data-set='{"min_acres":"<?= $a ?>","max_acres":"<?= $b ?>"}'><?= $t ?></button>
          <?php endforeach; ?>
        </div>
      <?php }]); ?>

      <?php component('filter_section', ['title' => 'Furnishing & condition', 'icon' => 'fa-couch', 'families' => 'residential commercial', 'body' => function () use ($chk, $f) { ?>
        <div class="flex flex-wrap gap-1.5"><?php foreach (OPT['furnishing'] as $k => $v): ?><label class="vk-choice"><input type="checkbox" name="furnishing[]" value="<?= $k ?>" <?= $chk('furnishing', $k) ?> data-filter><span><?= e($v) ?></span></label><?php endforeach; ?></div>
        <div class="flex flex-wrap gap-1.5"><?php foreach (OPT['condition'] as $k => $v): ?><label class="vk-choice"><input type="checkbox" name="condition[]" value="<?= $k ?>" <?= $chk('condition', $k) ?> data-filter><span><?= e($v) ?></span></label><?php endforeach; ?></div>
        <div data-families="residential" class="space-y-2 pt-1">
          <label class="vk-switch"><input type="checkbox" name="self_contained" value="1" <?= $f['self_contained'] ? 'checked' : '' ?> data-filter>Self-contained only</label>
          <label class="vk-switch"><input type="checkbox" name="pets" value="1" <?= $f['pets'] ? 'checked' : '' ?> data-filter>Pet friendly</label>
        </div>
      <?php }]); ?>

      <?php component('filter_section', ['title' => 'Parking', 'icon' => 'fa-square-parking', 'families' => 'commercial', 'body' => function () use ($f) { ?>
        <div class="flex flex-wrap gap-1.5"><?php foreach (['' => 'Any', '1' => '1+', '3' => '3+', '5' => '5+', '10' => '10+', '20' => '20+'] as $k => $v): ?><label class="vk-choice"><input type="radio" name="parking" value="<?= $k ?>" <?= (string)$f['parking'] === (string)$k ? 'checked' : '' ?> data-filter><span><?= $v ?></span></label><?php endforeach; ?></div>
      <?php }]); ?>

      <?php component('filter_section', ['title' => 'Ownership & title', 'icon' => 'fa-file-contract', 'families' => 'land', 'open' => true, 'body' => function () use ($chk, $f) { ?>
        <p class="text-xs font-bold text-muted">Tenure</p>
        <div class="flex flex-wrap gap-1.5"><?php foreach (OPT['tenure'] as $k => $v): if ($k === 'not_sure') continue; ?><label class="vk-choice"><input type="checkbox" name="tenure[]" value="<?= $k ?>" <?= $chk('tenure', $k) ?> data-filter><span><?= e($v) ?></span></label><?php endforeach; ?></div>
        <p class="text-xs font-bold text-muted pt-1">Document</p>
        <div class="flex flex-wrap gap-1.5"><?php foreach (OPT['title_status'] as $k => $v): ?><label class="vk-choice"><input type="checkbox" name="title_status[]" value="<?= $k ?>" <?= $chk('title_status', $k) ?> data-filter><span><?= e($v) ?></span></label><?php endforeach; ?></div>
        <label class="vk-switch pt-1"><input type="checkbox" name="payment_plan" value="1" <?= $f['payment_plan'] ? 'checked' : '' ?> data-filter>Payment plan available</label>
      <?php }]); ?>

      <?php component('filter_section', ['title' => 'Zoning & access', 'icon' => 'fa-road', 'families' => 'land', 'body' => function () use ($chk) { ?>
        <div class="flex flex-wrap gap-1.5"><?php foreach (OPT['zoning'] as $k => $v): ?><label class="vk-choice"><input type="checkbox" name="zoning[]" value="<?= $k ?>" <?= $chk('zoning', $k) ?> data-filter><span><?= e($v) ?></span></label><?php endforeach; ?></div>
        <div class="flex flex-wrap gap-1.5"><?php foreach (OPT['road_access'] as $k => $v): ?><label class="vk-choice"><input type="checkbox" name="road[]" value="<?= $k ?>" <?= $chk('road', $k) ?> data-filter><span><i class="fa-solid fa-road"></i><?= e($v) ?></span></label><?php endforeach; ?></div>
      <?php }]); ?>

      <?php component('filter_section', ['title' => 'Service options', 'icon' => 'fa-screwdriver-wrench', 'families' => 'service', 'open' => true, 'body' => function () use ($chk, $f) { ?>
        <div class="flex flex-wrap gap-1.5"><?php foreach (OPT['pricing_model'] as $k => $v): ?><label class="vk-choice"><input type="checkbox" name="pricing_model[]" value="<?= $k ?>" <?= $chk('pricing_model', $k) ?> data-filter><span><?= e($v) ?></span></label><?php endforeach; ?></div>
        <label class="vk-switch"><input type="checkbox" name="emergency" value="1" <?= $f['emergency'] ? 'checked' : '' ?> data-filter><i class="fa-solid fa-truck-medical text-rose-500"></i>24/7 emergency</label>
        <label class="vk-switch"><input type="checkbox" name="materials" value="1" <?= $f['materials'] ? 'checked' : '' ?> data-filter><i class="fa-solid fa-cubes text-muted"></i>Provides materials</label>
        <label class="vk-switch"><input type="checkbox" name="licensed" value="1" <?= $f['licensed'] ? 'checked' : '' ?> data-filter><i class="fa-solid fa-certificate text-sky-500"></i>Licensed (NCA, EPRA…)</label>
      <?php }]); ?>

      <?php foreach (['residential' => 'residential', 'short_stay' => 'short_stay', 'commercial' => 'commercial', 'land' => 'land'] as $ff => $fams): ?>
        <?php component('filter_section', ['title' => 'Features & amenities', 'icon' => 'fa-star', 'families' => $fams, 'body' => function () use ($ff, $chk) { $feats = features_for($ff); ?>
          <input type="search" class="vk-input !py-2 text-sm" placeholder="Find a feature…" data-feature-search>
          <div class="grid grid-cols-1 gap-0.5 max-h-72 overflow-y-auto pr-1" data-feature-list>
            <?php foreach ($feats as $ft): ?>
              <label class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-surface2 cursor-pointer text-sm" data-name="<?= e(strtolower($ft['name'])) ?>"><input type="checkbox" name="features[]" value="<?= $ft['id'] ?>" <?= $chk('features', $ft['id']) ?> class="h-4 w-4 accent-amber-600" data-filter><i class="fa-solid <?= e($ft['icon']) ?> w-4 text-muted"></i><?= e($ft['name']) ?></label>
            <?php endforeach; ?>
          </div>
        <?php }]); ?>
      <?php endforeach; ?>

      <?php component('filter_section', ['title' => 'Seller & listing', 'icon' => 'fa-user-check', 'open' => true, 'body' => function () use ($f, $chk) { ?>
        <label class="vk-switch"><input type="checkbox" name="official" value="1" <?= $f['official'] ? 'checked' : '' ?> data-filter><i class="fa-solid fa-crown text-amber-500"></i>Official Vikao only</label>
        <label class="vk-switch"><input type="checkbox" name="verified" value="1" <?= $f['verified'] ? 'checked' : '' ?> data-filter><i class="fa-solid fa-circle-check text-sky-500"></i>Verified sellers</label>
        <label class="vk-switch"><input type="checkbox" name="video" value="1" <?= $f['video'] ? 'checked' : '' ?> data-filter><i class="fa-solid fa-vr-cardboard text-violet-500"></i>Has 360° video tour</label>
        <label class="vk-switch"><input type="checkbox" name="negotiable" value="1" <?= $f['negotiable'] ? 'checked' : '' ?> data-filter><i class="fa-solid fa-handshake text-emerald-500"></i>Price negotiable</label>
        <label class="vk-switch" data-families="residential commercial short_stay"><input type="checkbox" name="no_sc" value="1" <?= $f['no_sc'] ? 'checked' : '' ?> data-filter><i class="fa-solid fa-receipt text-muted"></i>No service charge</label>
        <p class="text-xs font-bold text-muted pt-1">Listed by</p>
        <div class="flex flex-wrap gap-1.5"><?php foreach (OPT['listed_by'] as $k => $v): ?><label class="vk-choice"><input type="checkbox" name="listed_by[]" value="<?= $k ?>" <?= $chk('listed_by', $k) ?> data-filter><span><?= e($v) ?></span></label><?php endforeach; ?></div>
        <p class="text-xs font-bold text-muted pt-1">Seller membership</p>
        <div class="flex flex-wrap gap-1.5">
          <?php foreach (['gold' => ['Gold', 'fa-crown'], 'silver' => ['Silver', 'fa-award'], 'bronze' => ['Bronze', 'fa-medal']] as $k => [$v, $i]): ?><label class="vk-choice"><input type="checkbox" name="tier[]" value="<?= $k ?>" <?= $chk('tier', $k) ?> data-filter><span><i class="fa-solid <?= $i ?>"></i><?= $v ?></span></label><?php endforeach; ?>
        </div>
      <?php }]); ?>

      <?php component('filter_section', ['title' => 'Date posted', 'icon' => 'fa-clock', 'body' => function () use ($f) { ?>
        <div class="flex flex-wrap gap-1.5">
          <label class="vk-choice"><input type="radio" name="posted" value="" <?= !$f['posted'] ? 'checked' : '' ?> data-filter><span>Any time</span></label>
          <?php foreach (OPT['posted_within'] as $k => $v): ?><label class="vk-choice"><input type="radio" name="posted" value="<?= $k ?>" <?= (string)$f['posted'] === (string)$k ? 'checked' : '' ?> data-filter><span><?= e($v) ?></span></label><?php endforeach; ?>
        </div>
      <?php }]); ?>

      <div class="flex gap-2 sticky bottom-0 bg-canvas py-3 lg:static lg:py-0">
        <a href="<?= url('shop.php') ?>" class="vk-btn-ghost flex-1" id="reset-filters"><i class="fa-solid fa-rotate-left"></i>Reset</a>
        <button type="button" class="vk-btn-accent flex-1 lg:hidden" data-filters-close>Show <span id="result-count-m"><?= number_format($res['total']) ?></span> ads</button>
      </div>
    </form>
  </aside>

  <!-- ============== RESULTS ============== -->
  <section aria-live="polite">
    <div id="results" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
      <?php foreach ($res['rows'] as $l) component('ad_card', ['l' => $l, 'favs' => $favs]); ?>
    </div>
    <div id="no-results" class="<?= $res['total'] ? 'hidden' : '' ?>">
      <?php component('empty_state', ['icon' => 'fa-magnifying-glass', 'title' => 'No ads match your filters', 'text' => 'Try widening the price range, picking a nearby area or removing a few filters.', 'cta' => 'Reset filters', 'href' => url('shop.php')]); ?>
    </div>
    <div id="pagination"><?php component('pagination', ['page' => $res['page'], 'pages' => $res['pages']]); ?></div>
  </section>
</div>
<?php ob_start(); ?>
<script>
window.VK_SHOP = { family: <?= json_encode($family) ?>, deal: <?= json_encode($cat['deal_type'] ?? ($f['deal'] ?: null)) ?> };
</script>
<script src="<?= asset('js/shop.js') ?>"></script>
<?php $extraScripts = ob_get_clean(); include APP_ROOT . '/includes/layout/footer.php'; ?>
