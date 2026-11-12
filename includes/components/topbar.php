<?php
/** Top bar: logo (left) · Vikao name (centre) · account icons (right). */
$me = $me ?? current_user();
$script = basename($_SERVER['SCRIPT_NAME'] ?? '');
$icons = [
    ['profile.php',       'fa-regular fa-circle-user', 'My profile',   null],
    ['favourites.php',    'fa-regular fa-heart',       'Favourites',   null],
    ['chats.php',         'fa-regular fa-comments',    'Chats',        'chats'],
    ['orders.php',        'fa-solid fa-bag-shopping',  'My orders',    null],
    ['my-listings.php',   'fa-solid fa-rectangle-list','My listings',  null],
    ['notifications.php', 'fa-regular fa-bell',        'Notifications','notif'],
];
?>
<header class="sticky top-0 z-40 bg-canvas/85 backdrop-blur-xl border-b border-line/70">
  <div class="mx-auto max-w-7xl px-3 sm:px-6 h-16 grid grid-cols-[auto_1fr_auto] items-center gap-2 sm:gap-3">
    <!-- left: logo -->
    <a href="<?= url() ?>" class="flex items-center gap-2 shrink-0 group" aria-label="Vikao home">
      <img src="<?= asset('img/logo-mark.svg') ?>" alt="" class="h-10 w-10 rounded-xl shadow-soft transition group-hover:scale-105">
    </a>
    <!-- centre: name -->
    <a href="<?= url() ?>" class="justify-self-center font-display text-2xl sm:text-[1.7rem] font-bold tracking-tight leading-none">
      vikao<span class="text-accent">.</span>
    </a>
    <!-- right: icons -->
    <nav class="flex items-center gap-0.5 sm:gap-1" aria-label="Account">
      <button type="button" class="vk-icon-btn" data-theme-toggle aria-label="Toggle dark mode" title="Toggle theme">
        <i class="fa-solid fa-moon dark:hidden"></i><i class="fa-solid fa-sun hidden dark:inline"></i>
      </button>
      <?php foreach ($icons as [$href, $icon, $label, $badge]):
        $active = $script === $href;
        $hideMobile = !in_array($href, ['chats.php', 'notifications.php'], true); ?>
        <a href="<?= url($href) ?>" class="vk-icon-btn <?= $active ? 'is-active' : '' ?> <?= $hideMobile ? 'hidden md:inline-flex' : '' ?>" title="<?= e($label) ?>" aria-label="<?= e($label) ?>">
          <i class="<?= $icon ?>"></i>
          <?php if ($badge): ?><span class="vk-dot-badge" data-count="<?= $badge ?>" hidden></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <a href="<?= url('sell.php') ?>" class="ml-1 sm:ml-2 hidden sm:inline-flex items-center gap-2 rounded-xl bg-accent px-4 py-2.5 text-sm font-bold text-accentink shadow-soft hover:brightness-110 active:scale-[.98] transition">
        <i class="fa-solid fa-plus"></i><span>Sell</span>
      </a>
      <?php if (!$me): ?>
        <a href="<?= url('login.php') ?>" class="ml-1 hidden lg:inline-flex items-center gap-2 rounded-xl border border-line px-3.5 py-2.5 text-sm font-semibold hover:bg-surface2 transition">Sign in</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
