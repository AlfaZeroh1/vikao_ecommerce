<?php
require __DIR__ . '/includes/bootstrap.php';
if (is_logged_in()) redirect('index.php');
$pageTitle = 'Create account';
include APP_ROOT . '/includes/layout/header.php';
component('auth_shell_open', ['heading' => 'Create your account', 'sub' => 'Post ads for free, chat with buyers and track your reservations.']);
?>
<?php component('oauth_buttons'); ?>
<form id="register-form" class="space-y-4" novalidate>
  <input type="hidden" name="action" value="register">
  <div class="grid grid-cols-2 gap-3">
    <div data-field-wrap><label class="vk-label" for="first_name">First name <span class="req">*</span></label><input id="first_name" name="first_name" class="vk-input" autocomplete="given-name" required></div>
    <div data-field-wrap><label class="vk-label" for="last_name">Last name <span class="req">*</span></label><input id="last_name" name="last_name" class="vk-input" autocomplete="family-name" required></div>
  </div>
  <div data-field-wrap><label class="vk-label" for="email">Email <span class="req">*</span></label>
    <div class="vk-input-group"><i class="fa-regular fa-envelope"></i><input id="email" name="email" type="email" class="vk-input" autocomplete="email" placeholder="you@example.com"></div></div>
  <div data-field-wrap><label class="vk-label" for="phone">Mobile number <span class="req">*</span></label>
    <div class="flex gap-2"><span class="vk-input !w-auto flex items-center gap-2 font-semibold">🇰🇪 +254</span>
      <input id="phone" name="phone" type="tel" class="vk-input" autocomplete="tel" placeholder="712 345 678"></div></div>
  <div class="grid sm:grid-cols-2 gap-3">
    <div data-field-wrap><label class="vk-label" for="password">Password <span class="req">*</span></label><input id="password" name="password" type="password" class="vk-input" autocomplete="new-password" placeholder="8+ letters & numbers">
      <div class="mt-2 h-1.5 rounded-full bg-line overflow-hidden"><div id="pw-meter" class="h-full w-0 transition-all"></div></div></div>
    <div data-field-wrap><label class="vk-label" for="password_confirmation">Repeat password <span class="req">*</span></label><input id="password_confirmation" name="password_confirmation" type="password" class="vk-input" autocomplete="new-password"></div>
  </div>
  <div data-field-wrap>
    <label class="flex items-start gap-3 text-sm text-inksoft"><input type="checkbox" name="terms" value="1" class="mt-1 h-4 w-4 accent-amber-600">
      <span>I agree to Vikao's <a href="#" class="font-semibold text-accent">Terms</a> and <a href="#" class="font-semibold text-accent">Privacy Policy</a>, and to receive account notifications.</span></label>
  </div>
  <button class="vk-btn-accent w-full !py-3.5 text-base"><i class="fa-solid fa-user-plus"></i>Create account</button>
  <p class="text-center text-sm text-muted">Already have an account? <a href="<?= url('login.php') ?>" class="font-bold text-accent">Sign in</a></p>
</form>
<?php component('auth_shell_close'); ?>
<?php ob_start(); ?>
<script>
$('#password').on('input', function () {
  const v = this.value; let s = 0; if (v.length >= 8) s++; if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++; if (/\d/.test(v)) s++; if (/[^A-Za-z0-9]/.test(v)) s++;
  $('#pw-meter').css('width', (s * 25) + '%').attr('class', 'h-full transition-all ' + ['bg-rose-500', 'bg-rose-500', 'bg-amber-500', 'bg-lime-500', 'bg-emerald-500'][s]);
});
$('#register-form').on('submit', function (e) {
  e.preventDefault(); const $f = $(this), $b = $f.find('button.vk-btn-accent');
  VK.btnLoading($b, true, 'Creating account…');
  VK.api('auth.php', $f.serialize(), { silent: true }).then(r => { VK.toast(r.message); location.href = r.redirect; })
    .catch(j => { VK.btnLoading($b, false); VK.showErrors($f, j.errors); VK.toast(j.message || 'Could not create account', 'error'); });
});
</script>
<?php $extraScripts = ob_get_clean(); include APP_ROOT . '/includes/layout/footer.php'; ?>
