<?php
/** Badge pills for a user. Vars: $user (with tier_code/tier_name), $badges (rows), $size ('sm'|'md') */
$size = $size ?? 'sm';
$cls = $size === 'md' ? 'px-2.5 py-1 text-xs' : 'px-2 py-0.5 text-[11px]';
?>
<span class="inline-flex flex-wrap gap-1.5 align-middle">
  <?php if (($user['account_type'] ?? '') === 'official'): ?>
    <span class="vk-badge-official <?= $cls ?>"><i class="fa-solid fa-crown"></i>Official Vikao</span>
  <?php endif; ?>
  <?php foreach ($badges as $b): if ($b['code'] === 'official') continue; ?>
    <span class="inline-flex items-center gap-1 rounded-full font-bold <?= $cls ?>" style="background: <?= e($b['color_hex']) ?>1f; color: <?= e($b['color_hex']) ?>" title="<?= e($b['description']) ?>">
      <i class="fa-solid <?= e($b['icon']) ?>"></i><?= e($b['name']) ?>
    </span>
  <?php endforeach; ?>
  <?php if (!empty($user['tier_code']) && ($user['account_type'] ?? '') !== 'official'): ?>
    <span class="vk-tier vk-tier-<?= e($user['tier_code']) ?> <?= $cls ?>"><i class="fa-solid <?= e($user['tier_icon']) ?>"></i><?= e($user['tier_name']) ?></span>
  <?php endif; ?>
</span>
