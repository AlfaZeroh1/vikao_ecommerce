<div class="grid grid-cols-3 gap-2">
  <?php foreach ([['google', 'fa-google', 'text-[#EA4335]'], ['apple', 'fa-apple', ''], ['facebook', 'fa-facebook', 'text-[#1877F2]']] as [$p, $i, $c]): ?>
    <button type="button" class="vk-btn-ghost !py-3" data-oauth="<?= $p ?>" title="Continue with <?= ucfirst($p) ?>">
      <i class="fa-brands <?= $i ?> text-lg <?= $c ?>"></i><span class="hidden sm:inline"><?= ucfirst($p) ?></span>
    </button>
  <?php endforeach; ?>
</div>
<div class="my-5 flex items-center gap-3 text-xs font-semibold uppercase tracking-wider text-muted"><span class="h-px flex-1 bg-line"></span>or with email<span class="h-px flex-1 bg-line"></span></div>
<script>
document.addEventListener('click', function (e) {
  const b = e.target.closest('[data-oauth]'); if (!b) return;
  Swal.fire({ icon: 'info', title: 'Coming soon', text: b.dataset.oauth.charAt(0).toUpperCase() + b.dataset.oauth.slice(1) + ' sign-in is on the way. Use email for now.' });
});
</script>
