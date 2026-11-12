<?php
/** Vars: $page, $pages. Links carry data-page for AJAX; href keeps non-JS fallback. */
if ($pages <= 1) return;
$q = $_GET; unset($q['action']);
$link = function ($p) use ($q) { $q['page'] = $p; return url('shop.php', $q); };
$window = array_unique(array_filter([1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pages], fn($p) => $p >= 1 && $p <= $pages));
sort($window);
?>
<nav class="flex items-center justify-center gap-1.5 mt-10" aria-label="Pagination">
  <a href="<?= e($link(max(1, $page - 1))) ?>" data-page="<?= max(1, $page - 1) ?>" class="vk-btn-ghost !px-3 <?= $page <= 1 ? 'opacity-40 pointer-events-none' : '' ?>" aria-label="Previous"><i class="fa-solid fa-chevron-left"></i></a>
  <?php $prev = 0; foreach ($window as $p): if ($p - $prev > 1): ?><span class="px-1 text-muted">…</span><?php endif; ?>
    <a href="<?= e($link($p)) ?>" data-page="<?= $p ?>" class="<?= $p === $page ? 'vk-btn-dark' : 'vk-btn-ghost' ?> !px-3.5 min-w-[2.75rem]"><?= $p ?></a>
  <?php $prev = $p; endforeach; ?>
  <a href="<?= e($link(min($pages, $page + 1))) ?>" data-page="<?= min($pages, $page + 1) ?>" class="vk-btn-ghost !px-3 <?= $page >= $pages ? 'opacity-40 pointer-events-none' : '' ?>" aria-label="Next"><i class="fa-solid fa-chevron-right"></i></a>
</nav>
