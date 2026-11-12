<?php /** Vars: $icon, $title, $text, $cta (label), $href */ ?>
<div class="rounded-3xl border border-dashed border-line bg-surface/60 px-6 py-16 text-center">
  <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-accent/10 text-accent text-2xl"><i class="fa-solid <?= e($icon ?? 'fa-box-open') ?>"></i></div>
  <h3 class="mt-4 font-display text-xl font-bold"><?= e($title ?? 'Nothing here yet') ?></h3>
  <?php if (!empty($text)): ?><p class="mt-1 text-muted max-w-md mx-auto"><?= e($text) ?></p><?php endif; ?>
  <?php if (!empty($cta)): ?><a href="<?= e($href) ?>" class="vk-btn-accent mt-6 inline-flex"><?= e($cta) ?></a><?php endif; ?>
</div>
