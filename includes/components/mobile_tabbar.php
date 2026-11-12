<?php $s = basename($_SERVER['SCRIPT_NAME'] ?? ''); $tab = fn($f) => $s === $f ? 'text-accent' : 'text-muted'; ?>
<nav class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-surface/95 backdrop-blur border-t border-line pb-[env(safe-area-inset-bottom)]" aria-label="Quick">
  <div class="grid grid-cols-5 h-16 text-[11px] font-semibold">
    <a href="<?= url() ?>" class="flex flex-col items-center justify-center gap-1 <?= $tab('index.php') ?>"><i class="fa-solid fa-house text-lg"></i>Home</a>
    <a href="<?= url('shop.php') ?>" class="flex flex-col items-center justify-center gap-1 <?= $tab('shop.php') ?>"><i class="fa-solid fa-magnifying-glass text-lg"></i>Explore</a>
    <a href="<?= url('sell.php') ?>" class="flex flex-col items-center justify-center -mt-6"><span class="grid h-14 w-14 place-items-center rounded-2xl bg-accent text-accentink shadow-lift"><i class="fa-solid fa-plus text-xl"></i></span><span class="mt-1 text-ink">Sell</span></a>
    <a href="<?= url('favourites.php') ?>" class="flex flex-col items-center justify-center gap-1 <?= $tab('favourites.php') ?>"><i class="fa-solid fa-heart text-lg"></i>Saved</a>
    <a href="<?= url('profile.php') ?>" class="flex flex-col items-center justify-center gap-1 <?= $tab('profile.php') ?>"><i class="fa-solid fa-circle-user text-lg"></i>Me</a>
  </div>
</nav>
