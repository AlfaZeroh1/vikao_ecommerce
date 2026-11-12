<?php /** Vars: $step (1..4) */ $steps = ['Order details', 'Checkout', 'Pay', 'Done']; ?>
<div class="vk-steps mb-6 overflow-x-auto no-scrollbar">
  <?php foreach ($steps as $i => $s): $n = $i + 1; ?>
    <div class="vk-step <?= $n < $step ? 'done' : ($n === $step ? 'now' : '') ?> shrink-0"><b><?= $n < $step ? '<i class="fa-solid fa-check"></i>' : $n ?></b><span class="hidden sm:inline"><?= $s ?></span></div>
    <?php if ($n < count($steps)): ?><div class="vk-step-line"></div><?php endif; ?>
  <?php endforeach; ?>
</div>
