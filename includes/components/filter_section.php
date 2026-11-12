<?php
/** Collapsible filter group. Vars: $title, $icon, $body (callable), $open (bool), $families (space list|null), $deal (space list|null) */
$open = $open ?? false;
?>
<details class="vk-panel group/fs overflow-hidden" <?= $open ? 'open' : '' ?> <?= !empty($families) ? 'data-families="' . e($families) . '"' : '' ?> <?= !empty($deal) ? 'data-deals="' . e($deal) . '"' : '' ?>>
  <summary class="flex items-center gap-2.5 px-4 py-3.5 cursor-pointer list-none select-none font-bold text-sm [&::-webkit-details-marker]:hidden">
    <span class="grid h-7 w-7 place-items-center rounded-lg bg-accent/10 text-accent text-xs"><i class="fa-solid <?= e($icon) ?>"></i></span>
    <?= e($title) ?>
    <span class="ml-auto hidden rounded-full bg-accent px-1.5 text-[10px] font-extrabold text-accentink" data-section-count></span>
    <i class="fa-solid fa-chevron-down text-xs text-muted transition group-open/fs:rotate-180"></i>
  </summary>
  <div class="px-4 pb-4 space-y-2.5"><?php $body(); ?></div>
</details>
