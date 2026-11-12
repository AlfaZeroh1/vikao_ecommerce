<?php
require __DIR__ . '/includes/bootstrap.php';
$me = current_user();
$favs = favourite_ids(uid());

$official = listing_cards("l.status = 'active' AND l.is_official = 1", [], 'l.published_at DESC', 8);
$popular = listing_cards("l.status = 'active' AND l.is_official = 0", [],
    'l.is_premium DESC, (l.views_count + l.favourites_count * 5 + l.chats_count * 8 + l.phone_reveals_count * 3 + 20) / (DATEDIFF(NOW(), l.published_at) + 3) DESC', 8);

// "For you": families & regions the user engaged with (favourites + own location), else freshest
$forYou = []; $why = 'Fresh on Vikao';
if ($me) {
    $pref = one("SELECT GROUP_CONCAT(DISTINCT l.family) fams, GROUP_CONCAT(DISTINCT l.region_id) regs FROM favourites f JOIN listings l ON l.id = f.listing_id WHERE f.user_id = ?", [$me['id']]);
    $conds = []; $p = ['me' => $me['id']];
    if (!empty($pref['fams'])) $conds[] = "l.family IN ('" . implode("','", array_map(fn($x) => preg_replace('/\W/', '', $x), explode(',', $pref['fams']))) . "')";
    if (!empty($pref['regs'])) $conds[] = 'l.region_id IN (' . implode(',', array_map('intval', explode(',', $pref['regs']))) . ')';
    if ($conds) {
        $forYou = listing_cards("l.status = 'active' AND l.user_id <> :me AND (" . implode(' OR ', $conds) . ") AND l.id NOT IN (SELECT listing_id FROM favourites WHERE user_id = :me)", $p, 'l.published_at DESC', 8);
        $why = 'Based on what you saved';
    }
}
if (!$forYou) $forYou = listing_cards("l.status = 'active'", [], 'l.published_at DESC', 8);

$counts = [];
foreach (all("SELECT family, deal_type, COUNT(*) n FROM listings WHERE status = 'active' AND deleted_at IS NULL GROUP BY family, deal_type") as $r) {
    $counts[$r['family']] = ($counts[$r['family']] ?? 0) + (int)$r['n'];
    $counts[$r['family'] . ':' . $r['deal_type']] = (int)$r['n'];
}
$totalActive = array_sum(array_filter($counts, fn($k) => !str_contains($k, ':'), ARRAY_FILTER_USE_KEY));
$counties = all("SELECT id, name FROM locations WHERE country_id = 1 AND level = 1 ORDER BY name");
$popularAreas = all("SELECT l.id, l.name, p.id AS sub_id, p.parent_id AS county_id FROM locations l JOIN locations p ON p.id = l.parent_id WHERE l.is_popular = 1 AND l.level = 3 ORDER BY l.name LIMIT 16");
$services = array_slice(service_categories(), 0, 12);

$tiles = [
    ['residential-for-sale', 'Homes for sale', 'fa-house-circle-check', 'residential:sale', 'from-amber-400 to-orange-600'],
    ['residential-for-rent', 'Homes for rent', 'fa-key', 'residential:rent', 'from-orange-400 to-rose-500'],
    ['commercial-for-sale', 'Commercial for sale', 'fa-building-circle-check', 'commercial:sale', 'from-sky-400 to-indigo-600'],
    ['commercial-for-rent', 'Commercial to let', 'fa-store', 'commercial:rent', 'from-indigo-400 to-violet-600'],
    ['land-for-sale', 'Land & plots', 'fa-map-location-dot', 'land:sale', 'from-emerald-400 to-teal-600'],
    ['land-for-rent', 'Land for lease', 'fa-tractor', 'land:rent', 'from-lime-500 to-emerald-600'],
    ['short-stay', 'Short stays', 'fa-bed', 'short_stay:short_stay', 'from-fuchsia-400 to-rose-500'],
    ['services', 'Services', 'fa-screwdriver-wrench', 'service:service', 'from-slate-500 to-slate-800'],
];

$pageTitle = 'Homes, land & spaces in Kenya';
include APP_ROOT . '/includes/layout/header.php';
?>
<!-- HERO -->
<section class="relative overflow-hidden vk-hero text-white">
  <div class="absolute inset-0 vk-grid-bg [mask-image:radial-gradient(ellipse_at_center,black,transparent_75%)]"></div>
  <div class="relative mx-auto max-w-7xl px-4 sm:px-6 pt-12 pb-20 sm:pt-16 sm:pb-24">
    <div class="max-w-3xl">
      <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold tracking-wide ring-1 ring-white/15"><i class="fa-solid fa-bolt text-amber-400"></i><?= number_format($totalActive) ?> live listings across Kenya</span>
      <h1 class="mt-5 font-display text-4xl sm:text-6xl font-bold leading-[1.05] tracking-tight">Find a place that <span class="bg-gradient-to-r from-amber-300 to-amber-500 bg-clip-text text-transparent">feels like home.</span></h1>
      <p class="mt-4 text-lg text-slate-300 max-w-2xl">Buy, rent or lease homes, land and commercial spaces — book short stays and hire trusted property pros, all in one place.</p>
    </div>
    <form action="<?= url('shop.php') ?>" class="mt-8 rounded-3xl bg-white/95 dark:bg-slate-900/90 p-2.5 shadow-lift grid gap-2 md:grid-cols-[1.6fr_1fr_1fr_auto] text-slate-800 dark:text-slate-100 backdrop-blur">
      <label class="flex items-center gap-3 rounded-2xl px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
        <i class="fa-solid fa-magnifying-glass text-amber-600"></i>
        <span class="flex-1"><span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Search</span>
          <input name="q" class="w-full bg-transparent outline-none font-semibold placeholder:text-slate-400" placeholder="Estate, keyword or ad ref"></span>
      </label>
      <label class="flex items-center gap-3 rounded-2xl px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
        <i class="fa-solid fa-layer-group text-amber-600"></i>
        <span class="flex-1"><span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Category</span>
          <select name="cat" class="w-full bg-transparent outline-none font-semibold appearance-none cursor-pointer">
            <option value="">All categories</option>
            <?php foreach (root_categories() as $r): ?>
              <optgroup label="<?= e($r['name']) ?>">
                <?php if ($r['is_postable']): ?><option value="<?= e($r['slug']) ?>"><?= e($r['name']) ?></option><?php endif; ?>
                <?php foreach (category_children((int)$r['id']) as $ch): if ($r['family'] === 'service') continue; ?><option value="<?= e($ch['slug']) ?>"><?= e($r['name'] . ' · ' . ($ch['deal_type'] === 'sale' ? 'For sale' : 'For rent')) ?></option><?php endforeach; ?>
                <?php if ($r['family'] === 'service'): ?><option value="services">All services</option><?php endif; ?>
              </optgroup>
            <?php endforeach; ?>
          </select></span>
      </label>
      <label class="flex items-center gap-3 rounded-2xl px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
        <i class="fa-solid fa-location-dot text-amber-600"></i>
        <span class="flex-1"><span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">County</span>
          <select name="county" class="w-full bg-transparent outline-none font-semibold appearance-none cursor-pointer">
            <option value="">All of Kenya</option>
            <?php foreach ($counties as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
          </select></span>
      </label>
      <button class="vk-btn-accent !rounded-2xl !px-7 !py-4 text-base"><i class="fa-solid fa-magnifying-glass"></i>Search</button>
    </form>
    <div class="mt-5 flex flex-wrap gap-2">
      <?php foreach (array_slice($popularAreas, 0, 8) as $a): ?>
        <a href="<?= url('shop.php', ['county' => $a['county_id'], 'subcounty' => $a['sub_id'], 'area' => $a['id']]) ?>" class="rounded-full bg-white/10 hover:bg-white/20 px-3 py-1.5 text-xs font-semibold ring-1 ring-white/15 transition"><i class="fa-solid fa-location-dot text-amber-400 mr-1"></i><?= e($a['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CATEGORY TILES -->
<section class="mx-auto max-w-7xl px-4 sm:px-6 -mt-10 relative">
  <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
    <?php foreach ($tiles as [$slug, $label, $icon, $ck, $grad]): ?>
      <a href="<?= url('shop.php', ['cat' => $slug]) ?>" class="group vk-panel p-4 shadow-soft hover:-translate-y-1 hover:shadow-lift transition">
        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br <?= $grad ?> text-white text-lg shadow-soft group-hover:scale-110 transition"><i class="fa-solid <?= $icon ?>"></i></span>
        <span class="mt-3 block font-bold leading-tight"><?= e($label) ?></span>
        <span class="text-xs text-muted"><?= number_format($counts[$ck] ?? 0) ?> ads</span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($official): ?>
<!-- OFFICIAL STORE -->
<section class="mx-auto max-w-7xl px-4 sm:px-6 mt-14">
  <div class="rounded-4xl bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-500/10 dark:to-orange-500/5 ring-1 ring-amber-500/20 p-5 sm:p-8">
    <div class="flex flex-wrap items-end gap-4 mb-6">
      <div>
        <span class="vk-badge-official !text-xs"><i class="fa-solid fa-crown"></i>Official Vikao Store</span>
        <h2 class="mt-3 font-display text-2xl sm:text-3xl font-bold">Verified properties you can reserve today</h2>
        <p class="text-muted">Managed by Vikao · pay a reservation fee via M-Pesa · no middlemen.</p>
      </div>
      <a href="<?= url('shop.php', ['official' => 1]) ?>" class="ml-auto vk-btn-dark">View all <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <div class="flex gap-4 overflow-x-auto snap-x snap-mandatory no-scrollbar pb-2 -mx-1 px-1">
      <?php foreach ($official as $l): ?><div class="snap-start shrink-0 w-[82%] sm:w-[46%] lg:w-[23.5%]"><?php component('ad_card', ['l' => $l, 'favs' => $favs]); ?></div><?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- POPULAR -->
<section class="mx-auto max-w-7xl px-4 sm:px-6 mt-14">
  <div class="flex items-end justify-between mb-5">
    <div><h2 class="font-display text-2xl font-bold flex items-center gap-2"><i class="fa-solid fa-fire text-rose-500"></i>Popular right now</h2><p class="text-muted text-sm">Premium and most-viewed ads this week</p></div>
    <a href="<?= url('shop.php', ['sort' => 'popular']) ?>" class="text-sm font-bold text-accent hover:underline">See more <i class="fa-solid fa-arrow-right"></i></a>
  </div>
  <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach ($popular as $l) component('ad_card', ['l' => $l, 'favs' => $favs]); ?>
  </div>
</section>

<!-- SERVICES -->
<section class="mx-auto max-w-7xl px-4 sm:px-6 mt-14">
  <div class="vk-panel p-6 sm:p-8">
    <div class="flex items-end justify-between mb-5">
      <div><h2 class="font-display text-2xl font-bold flex items-center gap-2"><i class="fa-solid fa-screwdriver-wrench text-accent"></i>Property services</h2><p class="text-muted text-sm">From architects to movers — hire pros near you</p></div>
      <a href="<?= url('shop.php', ['cat' => 'services']) ?>" class="text-sm font-bold text-accent hover:underline">All <?= count(service_categories()) ?> services <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-3">
      <?php foreach ($services as $s): ?>
        <a href="<?= url('shop.php', ['cat' => $s['slug']]) ?>" class="group rounded-2xl border border-line p-4 text-center hover:border-accent hover:bg-accent/5 transition">
          <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-surface2 text-accent text-lg group-hover:bg-accent group-hover:text-accentink transition"><i class="fa-solid <?= e($s['icon']) ?>"></i></span>
          <span class="mt-2 block text-xs font-semibold leading-tight"><?= e($s['name']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- FOR YOU -->
<section class="mx-auto max-w-7xl px-4 sm:px-6 mt-14">
  <div class="flex items-end justify-between mb-5">
    <div><h2 class="font-display text-2xl font-bold flex items-center gap-2"><i class="fa-solid fa-wand-magic-sparkles text-violet-500"></i>For you</h2><p class="text-muted text-sm"><?= e($why) ?></p></div>
    <a href="<?= url('shop.php', ['sort' => 'newest']) ?>" class="text-sm font-bold text-accent hover:underline">Browse all <i class="fa-solid fa-arrow-right"></i></a>
  </div>
  <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach ($forYou as $l) component('ad_card', ['l' => $l, 'favs' => $favs]); ?>
  </div>
</section>

<!-- SELL CTA -->
<section class="mx-auto max-w-7xl px-4 sm:px-6 mt-16">
  <div class="relative overflow-hidden rounded-4xl bg-nav text-white p-8 sm:p-12 grid md:grid-cols-[1fr_auto] gap-6 items-center">
    <div class="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-amber-500/25 blur-3xl"></div>
    <div class="relative">
      <h2 class="font-display text-3xl font-bold">Have a property or service to list?</h2>
      <p class="mt-2 text-slate-300 max-w-xl">Post for free in under 5 minutes. Add photos, a 360° video tour, and reach thousands of serious buyers and tenants.</p>
      <div class="mt-4 flex flex-wrap gap-4 text-sm text-slate-300">
        <span><i class="fa-solid fa-circle-check text-emerald-400 mr-1"></i>Free to post</span>
        <span><i class="fa-solid fa-circle-check text-emerald-400 mr-1"></i>Reviewed for quality</span>
        <span><i class="fa-solid fa-circle-check text-emerald-400 mr-1"></i>Chat with buyers in-app</span>
      </div>
    </div>
    <a href="<?= url('sell.php') ?>" class="relative vk-btn-accent !px-8 !py-4 text-base"><i class="fa-solid fa-plus"></i>Post an ad</a>
  </div>
</section>

<!-- POPULAR LOCATIONS -->
<section class="mx-auto max-w-7xl px-4 sm:px-6 mt-14">
  <h2 class="font-display text-xl font-bold mb-4">Popular neighbourhoods</h2>
  <div class="flex flex-wrap gap-2">
    <?php foreach ($popularAreas as $a): ?>
      <a class="vk-chip" href="<?= url('shop.php', ['county' => $a['county_id'], 'subcounty' => $a['sub_id'], 'area' => $a['id']]) ?>"><i class="fa-solid fa-location-dot"></i><?= e($a['name']) ?></a>
    <?php endforeach; ?>
  </div>
</section>
<?php include APP_ROOT . '/includes/layout/footer.php'; ?>
