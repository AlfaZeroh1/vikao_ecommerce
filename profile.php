<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$badges = user_badges((int)$me['id']);
$stats = one("SELECT
    SUM(status = 'active') active_ads, SUM(status = 'pending') pending_ads, SUM(status IN ('closed','sold','expired')) closed_ads,
    COALESCE(SUM(views_count),0) views FROM listings WHERE user_id = ? AND deleted_at IS NULL", [$me['id']]);
$favCount = (int)val("SELECT COUNT(*) FROM favourites WHERE user_id = ?", [$me['id']]);
$orderCount = (int)val("SELECT COUNT(*) FROM orders WHERE user_id = ?", [$me['id']]);
$paidTotal = (float)val("SELECT COALESCE(SUM(amount_paid),0) FROM orders WHERE user_id = ? AND status IN ('paid','processing','completed')", [$me['id']]);
$chatCount = (int)val("SELECT COUNT(*) FROM conversations WHERE buyer_id = ? OR seller_id = ?", [$me['id'], $me['id']]);
$unreadChats = unread_chat_count((int)$me['id']);
$oauth = array_column(all("SELECT provider FROM user_oauth_accounts WHERE user_id = ?", [$me['id']]), 'provider');
$chain = $me['location_id'] ? location_chain((int)$me['location_id']) : [];
$counties = all("SELECT id, name FROM locations WHERE country_id = 1 AND level = 1 ORDER BY name");
$recent = all("SELECT action, subject_type, created_at FROM activity_log WHERE user_id = ? ORDER BY id DESC LIMIT 6", [$me['id']]);
$tier = $me['tier_code'] ? one("SELECT * FROM membership_tiers WHERE code = ?", [$me['tier_code']]) : null;

$pageTitle = 'My profile';
include APP_ROOT . '/includes/layout/header.php';
?>
<div class="mx-auto max-w-7xl px-4 sm:px-6 pt-8 space-y-6">
  <!-- hero card -->
  <section class="relative overflow-hidden rounded-4xl vk-hero text-white p-6 sm:p-8">
    <div class="absolute inset-0 vk-grid-bg opacity-50"></div>
    <div class="relative flex flex-col sm:flex-row sm:items-center gap-5">
      <span class="grid h-24 w-24 shrink-0 place-items-center rounded-3xl bg-gradient-to-br from-amber-400 to-orange-600 text-3xl font-extrabold text-slate-900 shadow-lift ring-4 ring-white/10"><?= e(initials($me['display_name'])) ?></span>
      <div class="min-w-0">
        <h1 class="font-display text-3xl font-bold"><?= e($me['display_name']) ?></h1>
        <p class="text-slate-300 text-sm"><i class="fa-regular fa-envelope"></i> <?= e($me['email']) ?> · <i class="fa-solid fa-phone"></i> <?= e($me['phone'] ?: '—') ?></p>
        <div class="mt-3"><?php component('seller_badges', ['user' => $me, 'badges' => $badges, 'size' => 'md']); ?></div>
      </div>
      <div class="sm:ml-auto flex flex-wrap gap-2">
        <a href="<?= url('sell.php') ?>" class="vk-btn-accent"><i class="fa-solid fa-plus"></i>Post an ad</a>
        <form method="post" action="<?= url('logout.php') ?>"><?= csrf_field() ?><button class="vk-btn !bg-white/10 hover:!bg-white/20 text-white"><i class="fa-solid fa-arrow-right-from-bracket"></i>Sign out</button></form>
      </div>
    </div>
    <p class="relative mt-4 text-xs text-slate-400">Member since <?= e(fdate($me['created_at'], 'F Y')) ?> · Account <?= e(ucfirst($me['account_type'])) ?><?= $me['role'] !== 'user' ? ' · ' . e(ucfirst($me['role'])) : '' ?></p>
  </section>

  <!-- stats -->
  <section class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
    <?php foreach ([
      ['fa-circle-check', 'Active ads', (int)$stats['active_ads'], 'my-listings.php?tab=active', 'text-emerald-500'],
      ['fa-hourglass-half', 'Under review', (int)$stats['pending_ads'], 'my-listings.php?tab=pending', 'text-amber-500'],
      ['fa-eye', 'Ad views', (int)$stats['views'], 'my-listings.php', 'text-sky-500'],
      ['fa-heart', 'Favourites', $favCount, 'favourites.php', 'text-rose-500'],
      ['fa-comments', 'Chats' . ($unreadChats ? " · $unreadChats new" : ''), $chatCount, 'chats.php', 'text-violet-500'],
      ['fa-bag-shopping', 'Orders', $orderCount, 'orders.php', 'text-amber-600'],
    ] as [$i, $lab, $n, $href, $c]): ?>
      <a href="<?= url($href) ?>" class="vk-panel p-4 hover:-translate-y-0.5 hover:shadow-soft transition">
        <i class="fa-solid <?= $i ?> <?= $c ?> text-lg"></i>
        <p class="mt-2 text-2xl font-extrabold"><?= number_format($n) ?></p>
        <p class="text-xs text-muted font-semibold"><?= e($lab) ?></p>
      </a>
    <?php endforeach; ?>
  </section>

  <div class="grid lg:grid-cols-[1fr_360px] gap-6 items-start">
    <div class="space-y-6">
      <!-- edit profile -->
      <form id="profile-form" class="vk-panel p-5 sm:p-7 space-y-4" novalidate>
        <input type="hidden" name="action" value="update">
        <h2 class="vk-section-title"><i class="fa-solid fa-user-pen"></i>Personal details</h2>
        <div class="grid sm:grid-cols-2 gap-3">
          <div data-field-wrap><label class="vk-label">First name</label><input name="first_name" value="<?= e($me['first_name']) ?>" class="vk-input"></div>
          <div data-field-wrap><label class="vk-label">Last name</label><input name="last_name" value="<?= e($me['last_name']) ?>" class="vk-input"></div>
          <div data-field-wrap><label class="vk-label">Display name <span class="text-muted font-normal">(shown on ads)</span></label><input name="display_name" value="<?= e($me['display_name']) ?>" class="vk-input"></div>
          <div data-field-wrap><label class="vk-label">Phone <?= $me['phone_verified_at'] ? '<span class="text-emerald-600"><i class="fa-solid fa-circle-check"></i> verified</span>' : '' ?></label><input name="phone" value="<?= e($me['phone']) ?>" class="vk-input"></div>
          <div data-field-wrap class="sm:col-span-2"><label class="vk-label">Email</label><input value="<?= e($me['email']) ?>" class="vk-input opacity-70" disabled></div>
        </div>
        <div class="grid sm:grid-cols-3 gap-3">
          <div><label class="vk-label">County</label><select name="county_id" class="vk-select" data-loc-level="1" data-loc-target="#p-sub"><option value="">—</option><?php foreach ($counties as $c): ?><option value="<?= $c['id'] ?>" <?= ($chain[0]['id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
          <div><label class="vk-label">Sub-county</label><select name="subcounty_id" id="p-sub" class="vk-select" data-loc-target="#p-area" data-placeholder="—" data-selected="<?= e($chain[1]['id'] ?? '') ?>" disabled><option value="">—</option></select></div>
          <div><label class="vk-label">Area</label><select name="area_id" id="p-area" class="vk-select" data-placeholder="—" data-selected="<?= e($chain[2]['id'] ?? '') ?>" disabled><option value="">—</option></select></div>
        </div>
        <div><label class="vk-label">About you</label><textarea name="bio" rows="3" maxlength="500" class="vk-textarea" placeholder="A short intro buyers will see on your ads"><?= e($me['bio']) ?></textarea></div>
        <button class="vk-btn-accent"><i class="fa-solid fa-floppy-disk"></i>Save changes</button>
      </form>

      <form id="password-form" class="vk-panel p-5 sm:p-7 space-y-4" novalidate>
        <input type="hidden" name="action" value="password">
        <h2 class="vk-section-title"><i class="fa-solid fa-key"></i>Password</h2>
        <div class="grid sm:grid-cols-3 gap-3">
          <div data-field-wrap><label class="vk-label">Current password</label><input type="password" name="current_password" class="vk-input" autocomplete="current-password"></div>
          <div data-field-wrap><label class="vk-label">New password</label><input type="password" name="new_password" class="vk-input" autocomplete="new-password"></div>
          <div data-field-wrap><label class="vk-label">Repeat new password</label><input type="password" name="new_password_confirmation" class="vk-input" autocomplete="new-password"></div>
        </div>
        <button class="vk-btn-dark"><i class="fa-solid fa-lock"></i>Update password</button>
      </form>
    </div>

    <aside class="space-y-6">
      <?php if ($tier): ?>
      <div class="vk-panel p-5 overflow-hidden relative">
        <div class="absolute -right-8 -top-8 h-28 w-28 rounded-full opacity-20" style="background: <?= e($tier['color_hex']) ?>"></div>
        <p class="text-xs font-bold uppercase tracking-wider text-muted">Membership</p>
        <p class="mt-1 flex items-center gap-2 text-xl font-extrabold"><i class="fa-solid <?= e($tier['icon']) ?>" style="color: <?= e($tier['color_hex']) ?>"></i><?= e($tier['name']) ?></p>
        <ul class="mt-3 space-y-1.5 text-sm">
          <?php foreach (json_decode($tier['perks'] ?? '[]', true) ?: [] as $perk): ?><li><i class="fa-solid fa-check text-emerald-500 mr-1.5"></i><?= e($perk) ?></li><?php endforeach; ?>
        </ul>
        <p class="mt-3 text-xs text-muted">Renews <?= e(fdate($me['membership_expires_at'])) ?></p>
      </div>
      <?php endif; ?>

      <div class="vk-panel p-5">
        <p class="font-bold mb-3"><i class="fa-solid fa-link text-accent mr-1"></i>Connected accounts</p>
        <?php foreach ([['google', 'fa-google', 'text-[#EA4335]'], ['apple', 'fa-apple', ''], ['facebook', 'fa-facebook', 'text-[#1877F2]']] as [$p, $i, $c]): $on = in_array($p, $oauth, true); ?>
          <div class="flex items-center gap-3 py-2.5 border-b border-line/60 last:border-0">
            <i class="fa-brands <?= $i ?> <?= $c ?> text-xl w-6 text-center"></i><span class="font-semibold"><?= ucfirst($p) ?></span>
            <span class="ml-auto text-xs font-bold <?= $on ? 'text-emerald-600' : 'text-muted' ?>"><?= $on ? 'Connected' : 'Coming soon' ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="vk-panel p-5">
        <p class="font-bold mb-3"><i class="fa-solid fa-palette text-accent mr-1"></i>Appearance</p>
        <div class="grid grid-cols-3 gap-2">
          <?php foreach (['light' => ['Light', 'fa-sun'], 'dark' => ['Dark', 'fa-moon'], 'system' => ['System', 'fa-desktop']] as $k => [$lab, $i]): ?>
            <label class="vk-choice"><input type="radio" name="theme" value="<?= $k ?>" <?= $me['theme'] === $k ? 'checked' : '' ?>><span class="w-full justify-center"><i class="fa-solid <?= $i ?>"></i><?= $lab ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="vk-panel p-5">
        <p class="font-bold mb-3"><i class="fa-solid fa-wallet text-accent mr-1"></i>Spending</p>
        <p class="text-2xl font-extrabold"><?= e(money($paidTotal)) ?></p><p class="text-xs text-muted">Reservation fees paid to Vikao</p>
      </div>

      <?php if ($recent): ?>
      <div class="vk-panel p-5">
        <p class="font-bold mb-3"><i class="fa-solid fa-clock-rotate-left text-accent mr-1"></i>Recent activity</p>
        <ul class="space-y-2 text-sm">
          <?php foreach ($recent as $a): ?><li class="flex justify-between gap-2"><span class="text-inksoft"><?= e(ucwords(str_replace(['.', '_'], ' ', $a['action']))) ?></span><span class="text-xs text-muted shrink-0"><?= e(time_ago($a['created_at'])) ?></span></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </aside>
  </div>
</div>
<?php ob_start(); ?>
<script>
function bind(id) {
  $(id).on('submit', function (e) {
    e.preventDefault(); const $f = $(this), $b = $f.find('button').last();
    VK.btnLoading($b, true);
    VK.api('profile.php', $f.serialize(), { silent: true }).then(r => { VK.btnLoading($b, false); VK.showErrors($f, {}); VK.toast(r.message); if (id === '#password-form') $f[0].reset(); })
      .catch(j => { VK.btnLoading($b, false); VK.showErrors($f, j.errors); VK.toast(j.message, 'error'); });
  });
}
bind('#profile-form'); bind('#password-form');
$('input[name=theme]').on('change', function () {
  const t = this.value; try { localStorage.setItem('vk-theme', t); } catch (e) {}
  const dark = t === 'dark' || (t === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
  document.documentElement.classList.toggle('dark', dark);
  VK.api('profile.php', { action: 'theme', theme: t }).then(() => VK.toast('Theme updated'));
});
</script>
<?php $extraScripts = ob_get_clean(); include APP_ROOT . '/includes/layout/footer.php'; ?>
