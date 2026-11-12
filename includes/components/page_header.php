<?php /** Vars: $icon, $title, $subtitle, $actions (html) */ ?>
<div class="mx-auto max-w-7xl px-4 sm:px-6 pt-8 pb-6 flex flex-col sm:flex-row sm:items-end gap-4">
  <div class="flex items-center gap-4">
    <span class="grid h-14 w-14 place-items-center rounded-2xl bg-nav text-amber-400 text-xl shadow-soft"><i class="<?= e($icon) ?>"></i></span>
    <div>
      <h1 class="font-display text-2xl sm:text-3xl font-bold tracking-tight"><?= e($title) ?></h1>
      <?php if (!empty($subtitle)): ?><p class="text-muted"><?= $subtitle ?></p><?php endif; ?>
    </div>
  </div>
  <?php if (!empty($actions)): ?><div class="sm:ml-auto flex flex-wrap gap-2"><?= $actions ?></div><?php endif; ?>
</div>
