<?php
/** Order / listing summary card. Vars: $title, $cover, $location, $ref, $lines (array of [label, value, bold?]), $total ([label, value]) */
?>
<div class="vk-panel p-5 sm:p-6 shadow-soft">
  <div class="flex gap-4">
    <img src="<?= e(media_url($cover)) ?>" alt="" class="h-20 w-24 rounded-2xl object-cover shrink-0">
    <div class="min-w-0">
      <span class="vk-badge-official"><i class="fa-solid fa-crown"></i>Official Vikao</span>
      <p class="mt-1.5 font-bold leading-snug line-clamp-2"><?= e($title) ?></p>
      <p class="text-xs text-muted truncate"><i class="fa-solid fa-location-dot"></i> <?= e($location) ?> · <?= e($ref) ?></p>
    </div>
  </div>
  <dl class="mt-5 space-y-2.5 text-sm">
    <?php foreach ($lines as $ln): ?>
      <div class="flex justify-between gap-3"><dt class="text-muted"><?= e($ln[0]) ?></dt><dd class="text-right <?= !empty($ln[2]) ? 'font-bold' : 'font-semibold' ?>"><?= e($ln[1]) ?></dd></div>
    <?php endforeach; ?>
  </dl>
  <div class="mt-4 pt-4 border-t border-dashed border-line flex items-center justify-between">
    <span class="font-bold"><?= e($total[0]) ?></span><span class="font-display text-2xl font-bold text-accent"><?= e($total[1]) ?></span>
  </div>
  <p class="mt-3 text-xs text-muted flex gap-2"><i class="fa-solid fa-shield-halved text-emerald-500 mt-0.5"></i>Your reservation fee is deducted from the final amount and is refundable if Vikao can't complete the deal.</p>
</div>
