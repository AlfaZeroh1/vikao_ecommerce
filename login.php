<?php
require __DIR__ . '/includes/bootstrap.php';
if (is_logged_in()) redirect('index.php');
$pageTitle = 'Sign in';
include APP_ROOT . '/includes/layout/header.php';
component('auth_shell_open', ['heading' => 'Welcome back', 'sub' => 'Sign in to chat with sellers, save favourites and reserve official listings.']);
?>
<?php component('oauth_buttons'); ?>
<form id="login-form" class="space-y-4" novalidate>
  <input type="hidden" name="action" value="login">
  <input type="hidden" name="next" value="<?= e((string)($_GET['next'] ?? '')) ?>">
  <div data-field-wrap>
    <label class="vk-label" for="email">Email address</label>
    <div class="vk-input-group"><i class="fa-regular fa-envelope"></i><input id="email" name="email" type="email" class="vk-input" autocomplete="email" placeholder="you@example.com" required></div>
  </div>
  <div data-field-wrap>
    <div class="flex items-center justify-between"><label class="vk-label" for="password">Password</label><a href="#" class="text-xs font-semibold text-accent" onclick="VK.toast('Password reset by email is coming soon.','info');return false;">Forgot?</a></div>
    <div class="vk-input-group"><i class="fa-solid fa-lock"></i><input id="password" name="password" type="password" class="vk-input pr-11" autocomplete="current-password" placeholder="••••••••" required>
      <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-muted" data-toggle-pass aria-label="Show password"><i class="fa-regular fa-eye"></i></button></div>
  </div>
  <button class="vk-btn-accent w-full !py-3.5 text-base"><i class="fa-solid fa-arrow-right-to-bracket"></i>Sign in</button>
  <p class="text-center text-sm text-muted">New to Vikao? <a href="<?= url('register.php') ?>" class="font-bold text-accent">Create an account</a></p>
</form>
<?php component('auth_shell_close'); ?>
<?php ob_start(); ?>
<script>
$('#login-form').on('submit', function (e) {
  e.preventDefault(); const $f = $(this), $b = $f.find('button.vk-btn-accent');
  VK.btnLoading($b, true, 'Signing in…');
  VK.api('auth.php', $f.serialize(), { silent: true }).then(r => { VK.toast(r.message); location.href = r.redirect; })
    .catch(j => { VK.btnLoading($b, false); VK.showErrors($f, j.errors); if (!j.errors) VK.toast(j.message || 'Could not sign in', 'error'); });
});
</script>
<?php $extraScripts = ob_get_clean(); include APP_ROOT . '/includes/layout/footer.php'; ?>
