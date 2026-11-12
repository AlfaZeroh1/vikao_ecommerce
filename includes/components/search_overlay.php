<div id="vk-search" class="fixed inset-0 z-[60] hidden" role="dialog" aria-modal="true" aria-label="Search">
  <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-search-close></div>
  <div class="relative mx-auto mt-20 w-[min(720px,calc(100%-2rem))] rounded-3xl bg-surface p-4 shadow-lift border border-line">
    <form action="<?= url('shop.php') ?>" class="flex items-center gap-3">
      <i class="fa-solid fa-magnifying-glass text-muted text-lg pl-2"></i>
      <input name="q" type="search" class="flex-1 bg-transparent text-lg outline-none placeholder:text-muted" placeholder="Search Kilimani apartments, plots in Kitengela, plumbers…" autocomplete="off">
      <button class="vk-btn-accent">Search</button>
    </form>
    <div class="mt-4 flex flex-wrap gap-2 text-sm">
      <?php foreach ([['2 bedroom Kilimani', 'fa-building'], ['Bedsitter Ruaka', 'fa-bed'], ['1/8 acre Kitengela', 'fa-map'], ['Office Westlands', 'fa-briefcase'], ['Airbnb Diani', 'fa-umbrella-beach'], ['Plumber', 'fa-faucet-drip']] as [$t, $i]): ?>
        <a href="<?= url('shop.php', ['q' => $t]) ?>" class="vk-chip"><i class="fa-solid <?= $i ?>"></i><?= e($t) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
