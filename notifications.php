<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$filter = ($_GET['f'] ?? '') === 'unread' ? 'unread' : 'all';
$rows = all("SELECT * FROM notifications WHERE user_id = ? " . ($filter === 'unread' ? 'AND read_at IS NULL ' : '') . "ORDER BY created_at DESC, id DESC LIMIT 200", [$me['id']]);
$unread = (int)val("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL", [$me['id']]);
$groups = [];
foreach ($rows as $n) {
    $d = date('Y-m-d', strtotime($n['created_at']));
    $label = $d === date('Y-m-d') ? 'Today' : ($d === date('Y-m-d', strtotime('-1 day')) ? 'Yesterday' : (strtotime($d) > strtotime('-7 days') ? 'This week' : 'Earlier'));
    $groups[$label][] = $n;
}
$tone = ['ad_approved' => 'bg-emerald-500/15 text-emerald-600', 'ad_rejected' => 'bg-rose-500/15 text-rose-600', 'new_message' => 'bg-violet-500/15 text-violet-600',
         'order_paid' => 'bg-amber-500/15 text-amber-600', 'order_received' => 'bg-amber-500/15 text-amber-600', 'welcome' => 'bg-sky-500/15 text-sky-600'];

$pageTitle = 'Notifications';
include APP_ROOT . '/includes/layout/header.php';
component('page_header', ['icon' => 'fa-solid fa-bell', 'title' => 'Notifications', 'subtitle' => $unread ? "<b class=\"text-accent\">$unread unread</b>" : 'You\'re all caught up',
    'actions' => ($unread ? '<button type="button" class="vk-btn-ghost" id="read-all"><i class="fa-solid fa-check-double"></i>Mark all as read</button>' : '')]);
?>
<div class="mx-auto max-w-3xl px-4 sm:px-6">
  <div class="flex gap-2 mb-4">
    <a href="<?= url('notifications.php') ?>" class="<?= $filter === 'all' ? 'vk-btn-dark' : 'vk-btn-ghost' ?> !py-2">All</a>
    <a href="<?= url('notifications.php', ['f' => 'unread']) ?>" class="<?= $filter === 'unread' ? 'vk-btn-dark' : 'vk-btn-ghost' ?> !py-2">Unread <span class="rounded-full bg-accent px-1.5 text-[10px] text-accentink"><?= $unread ?></span></a>
  </div>
  <?php if (!$rows): component('empty_state', ['icon' => 'fa-bell-slash', 'title' => 'No notifications', 'text' => 'Approvals, messages and order updates will show up here.']); endif; ?>
  <?php foreach ($groups as $label => $items): ?>
    <h3 class="mt-6 mb-2 text-xs font-bold uppercase tracking-wider text-muted"><?= e($label) ?></h3>
    <div class="vk-panel divide-y divide-line/70 overflow-hidden">
      <?php foreach ($items as $n): $isNew = !$n['read_at']; ?>
        <div class="group flex gap-3 p-4 hover:bg-surface2/60 transition <?= $isNew ? 'bg-accent/[.04]' : '' ?>" data-notif="<?= (int)$n['id'] ?>">
          <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl <?= $tone[$n['type']] ?? 'bg-surface2 text-muted' ?>"><i class="fa-solid <?= e($n['icon'] ?: 'fa-bell') ?>"></i></span>
          <a href="<?= e($n['link_url'] ?: '#') ?>" class="flex-1 min-w-0" data-open>
            <p class="font-bold leading-snug <?= $isNew ? '' : 'text-inksoft' ?>"><?= e($n['title']) ?></p>
            <?php if ($n['body']): ?><p class="text-sm text-muted line-clamp-2"><?= e($n['body']) ?></p><?php endif; ?>
            <p class="mt-1 text-[11px] font-semibold text-muted"><i class="fa-regular fa-clock"></i> <?= e(time_ago($n['created_at'])) ?></p>
          </a>
          <div class="flex flex-col items-end gap-2">
            <?php if ($isNew): ?><span class="h-2.5 w-2.5 rounded-full bg-accent" title="Unread"></span><?php endif; ?>
            <button type="button" class="opacity-0 group-hover:opacity-100 text-muted hover:text-rose-500 transition" data-del aria-label="Delete"><i class="fa-regular fa-trash-can"></i></button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php ob_start(); ?>
<script>
$('[data-open]').on('click', function (e) {
  const $n = $(this).closest('[data-notif]'), href = $(this).attr('href');
  e.preventDefault();
  $.post(VK.url('api/notifications.php'), { action: 'read', id: $n.data('notif') }).always(() => { if (href && href !== '#') location.href = href; else location.reload(); });
});
$('[data-del]').on('click', function () {
  const $n = $(this).closest('[data-notif]');
  VK.api('notifications.php', { action: 'delete', id: $n.data('notif') }).then(() => $n.slideUp(150));
});
$('#read-all').on('click', () => VK.api('notifications.php', { action: 'read_all' }).then(r => { VK.toast(r.message); setTimeout(() => location.reload(), 400); }));
</script>
<?php $extraScripts = ob_get_clean(); include APP_ROOT . '/includes/layout/footer.php'; ?>
