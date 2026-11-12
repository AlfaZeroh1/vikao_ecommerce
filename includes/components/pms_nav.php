<?php
$pendingCount = (int)val("SELECT COUNT(*) FROM listings WHERE status = 'pending' AND deleted_at IS NULL");
$newEvents = (int)val("SELECT COUNT(*) FROM pms_events WHERE status = 'delivered'");
?>
<div class="bg-gradient-to-r from-amber-500 to-orange-600 text-slate-900">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 h-12 flex items-center gap-2 text-sm font-bold overflow-x-auto no-scrollbar">
    <span class="flex items-center gap-2 mr-3 shrink-0"><i class="fa-solid fa-building-shield"></i>Vikao PMS <span class="rounded bg-slate-900/15 px-1.5 text-[10px] uppercase">mock</span></span>
    <a href="<?= url('pms/index.php') ?>" class="shrink-0 rounded-lg px-3 py-1.5 <?= ($active ?? '') === 'review' ? 'bg-slate-900 text-amber-400' : 'hover:bg-slate-900/10' ?>"><i class="fa-solid fa-list-check"></i> Ad review <?php if ($pendingCount): ?><span class="ml-1 rounded-full bg-rose-600 px-1.5 text-[10px] text-white"><?= $pendingCount ?></span><?php endif; ?></a>
    <a href="<?= url('pms/post.php') ?>" class="shrink-0 rounded-lg px-3 py-1.5 <?= ($active ?? '') === 'post' ? 'bg-slate-900 text-amber-400' : 'hover:bg-slate-900/10' ?>"><i class="fa-solid fa-crown"></i> Official store</a>
    <span class="ml-auto shrink-0 text-xs font-semibold opacity-80"><i class="fa-solid fa-satellite-dish"></i> <?= $newEvents ?> unacknowledged events</span>
  </div>
</div>
