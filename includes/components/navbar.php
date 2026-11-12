<?php
/** Category navbar — generated from the categories table. */
$roots = root_categories();
$curCat = (string)($_GET['cat'] ?? '');
$curFamily = ($c = $curCat ? category_by_slug($curCat) : null) ? $c['family'] : null;
?>
<nav class="sticky top-16 z-30 bg-nav text-navink shadow-[0_8px_24px_-16px_rgb(0_0_0/.5)]" aria-label="Categories">
  <div class="mx-auto max-w-7xl px-2 sm:px-6 md:relative">
    <ul class="flex items-center gap-1 overflow-x-auto md:overflow-visible no-scrollbar h-12 text-sm font-semibold">
      <li><a href="<?= url('shop.php') ?>" class="vk-nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'shop.php' && !$curCat ? 'is-active' : '' ?>"><i class="fa-solid fa-grip"></i>All</a></li>
      <?php foreach ($roots as $root):
        $children = category_children((int)$root['id']);
        $isActive = $curFamily === $root['family'];
        $isServices = $root['family'] === 'service'; ?>
        <li class="<?= $isServices ? '' : 'md:relative' ?> vk-dd" data-dd>
          <?php if (!$children): ?>
            <a href="<?= url('shop.php', ['cat' => $root['slug']]) ?>" class="vk-nav-link <?= $isActive ? 'is-active' : '' ?>"><i class="fa-solid <?= e($root['icon']) ?>"></i><?= e($root['name']) ?></a>
          <?php else: ?>
            <button type="button" class="vk-nav-link <?= $isActive ? 'is-active' : '' ?>" data-dd-toggle aria-expanded="false">
              <i class="fa-solid <?= e($root['icon']) ?>"></i><?= e($root['name']) ?><i class="fa-solid fa-chevron-down text-[10px] opacity-60"></i>
            </button>
            <?php if ($isServices): ?>
              <div class="vk-dd-panel vk-dd-wide p-4" data-dd-panel>
                <div class="flex items-center justify-between mb-3 px-1">
                  <div><p class="font-display font-bold text-ink text-base">Property services</p><p class="text-xs text-muted">Trusted pros for every stage — build, move, maintain.</p></div>
                  <a href="<?= url('shop.php', ['cat' => 'services']) ?>" class="text-xs font-bold text-accent hover:underline">All services <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-1 max-h-[60vh] overflow-y-auto pr-1">
                  <?php foreach ($children as $ch): ?>
                    <a href="<?= url('shop.php', ['cat' => $ch['slug']]) ?>" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13px] font-medium text-inksoft hover:bg-surface2 hover:text-ink transition">
                      <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-accent/10 text-accent"><i class="fa-solid <?= e($ch['icon']) ?>"></i></span>
                      <span class="leading-tight"><?= e($ch['name']) ?></span>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php else: ?>
              <div class="vk-dd-panel p-2" data-dd-panel>
                <?php foreach ($children as $ch): ?>
                  <a href="<?= url('shop.php', ['cat' => $ch['slug']]) ?>" class="flex items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-surface2 transition">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-accent/10 text-accent"><i class="fa-solid <?= e($ch['icon']) ?>"></i></span>
                    <span><span class="block font-semibold text-ink"><?= $ch['deal_type'] === 'sale' ? 'For Sale' : ($root['family'] === 'land' ? 'For Lease' : 'For Rent') ?></span>
                      <span class="block text-xs text-muted font-normal"><?= e($ch['description']) ?></span></span>
                  </a>
                <?php endforeach; ?>
                <a href="<?= url('shop.php', ['cat' => $root['slug']]) ?>" class="mt-1 flex items-center justify-between rounded-xl px-3 py-2 text-xs font-bold text-accent hover:bg-accent/10">Browse all <?= e(strtolower($root['name'])) ?> <i class="fa-solid fa-arrow-right"></i></a>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
      <li class="ml-auto hidden md:block"><a href="<?= url('shop.php', ['official' => 1]) ?>" class="vk-nav-link text-amber-300"><i class="fa-solid fa-crown"></i>Official Store</a></li>
      <li class="ml-auto md:ml-0"><button type="button" class="vk-nav-link" data-search-open aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i><span class="hidden lg:inline">Search</span></button></li>
    </ul>
  </div>
</nav>
