<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$active = null;
if (!empty($_GET['c'])) {
    $conv = conversation_for_user((string)$_GET['c'], (int)$me['id']);
    if ($conv) {
        $active = one("SELECT c.uuid, l.uuid AS listing_uuid, l.title, l.price, l.currency_code, l.price_unit, l.billing_period, l.status, l.ref_code, m.thumb_path,
            o.display_name AS other_name, o.id AS other_id, CASE WHEN c.buyer_id = ? THEN 'buying' ELSE 'selling' END AS role
            FROM conversations c JOIN listings l ON l.id = c.listing_id LEFT JOIN listing_media m ON m.id = l.cover_media_id
            JOIN users o ON o.id = CASE WHEN c.buyer_id = ? THEN c.seller_id ELSE c.buyer_id END WHERE c.id = ?", [$me['id'], $me['id'], $conv['id']]);
        $active['other_badges'] = user_badges((int)$active['other_id']);
    }
}
$pageTitle = 'Chats';
$mainClass = 'pb-24 md:pb-8';
include APP_ROOT . '/includes/layout/header.php';
?>
<div class="mx-auto max-w-7xl px-0 sm:px-6 pt-0 sm:pt-6">
  <div class="vk-panel !rounded-none sm:!rounded-3xl overflow-hidden grid md:grid-cols-[360px_1fr] h-[calc(100dvh-7rem-4rem)] md:h-[calc(100dvh-7rem-3rem)] min-h-[520px]">
    <!-- LIST -->
    <section id="chat-list-pane" class="border-r border-line flex flex-col min-h-0 <?= $active ? 'hidden md:flex' : 'flex' ?>">
      <div class="p-4 border-b border-line space-y-3">
        <div class="flex items-center gap-2"><h1 class="font-display text-xl font-bold">Chats</h1><span class="vk-dot-badge !static !border-0" data-count="chats" hidden></span></div>
        <div class="vk-input-group"><i class="fa-solid fa-magnifying-glass"></i><input id="chat-search" class="vk-input !py-2.5" placeholder="Search by ad or name"></div>
        <div class="flex gap-1.5 text-xs">
          <?php foreach (['all' => 'All', 'buying' => 'Buying', 'selling' => 'Selling', 'unread' => 'Unread'] as $k => $v): ?>
            <button type="button" class="vk-chip <?= $k === 'all' ? '!bg-nav !text-navink !border-nav' : '' ?>" data-chat-filter="<?= $k ?>"><?= $v ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <ul id="chat-list" class="flex-1 overflow-y-auto divide-y divide-line/60">
        <?php for ($i = 0; $i < 5; $i++): ?><li class="p-4 flex gap-3"><div class="vk-skel h-12 w-12"></div><div class="flex-1 space-y-2"><div class="vk-skel h-3 w-2/3"></div><div class="vk-skel h-3 w-1/2"></div></div></li><?php endfor; ?>
      </ul>
    </section>

    <!-- THREAD -->
    <section id="thread-pane" class="flex flex-col min-h-0 bg-canvas/60 <?= $active ? 'flex' : 'hidden md:flex' ?>">
      <?php if (!$active): ?>
        <div class="m-auto text-center px-6">
          <div class="mx-auto grid h-20 w-20 place-items-center rounded-3xl bg-accent/10 text-accent text-3xl"><i class="fa-regular fa-comments"></i></div>
          <h2 class="mt-4 font-display text-xl font-bold">Select a conversation</h2>
          <p class="text-muted max-w-sm mx-auto">Chats are tied to ads — open any listing and tap <b>Start chat with seller</b> to begin.</p>
        </div>
      <?php else: ?>
        <header class="flex items-center gap-3 p-3 sm:p-4 border-b border-line bg-surface">
          <a href="<?= url('chats.php') ?>" class="vk-icon-btn md:hidden" aria-label="Back"><i class="fa-solid fa-arrow-left"></i></a>
          <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-slate-600 to-slate-800 text-white font-bold"><?= e(initials($active['other_name'])) ?></span>
          <div class="min-w-0">
            <p class="font-bold truncate flex items-center gap-1.5"><?= e($active['other_name']) ?>
              <?php foreach ($active['other_badges'] as $b): if ($b['code'] === 'verified'): ?><i class="fa-solid fa-circle-check text-sky-500 text-sm" title="Verified"></i><?php endif; endforeach; ?></p>
            <p class="text-xs text-muted" id="presence"><?= $active['role'] === 'buying' ? 'Seller' : 'Interested buyer' ?></p>
          </div>
          <a href="<?= url('ad.php', ['id' => $active['listing_uuid']]) ?>" class="ml-auto flex items-center gap-3 rounded-2xl border border-line bg-canvas p-1.5 pr-3 max-w-[50%] hover:border-accent transition">
            <img src="<?= e(media_url($active['thumb_path'])) ?>" class="h-10 w-12 rounded-xl object-cover" alt="">
            <span class="min-w-0 hidden sm:block"><span class="block text-xs font-bold truncate"><?= e($active['title']) ?></span><span class="block text-xs text-accent font-extrabold"><?= e(money($active['price'], $active['currency_code'], true)) ?><span class="text-muted font-medium"><?= e(price_suffix($active)) ?></span></span></span>
          </a>
        </header>
        <?php if ($active['status'] !== 'active'): ?><div class="bg-amber-500/10 text-amber-700 dark:text-amber-300 text-xs font-semibold px-4 py-2"><i class="fa-solid fa-circle-info"></i> This ad is no longer active.</div><?php endif; ?>
        <div id="messages" class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-2"></div>
        <div class="px-3 sm:px-4 pt-2 flex gap-1.5 overflow-x-auto no-scrollbar" id="quick">
          <?php foreach (($active['role'] === 'buying' ? ['Is this still available?', 'When can I view it?', 'What\'s your best price?', 'Where exactly is it located?'] : ['Yes, it\'s still available.', 'When would you like to view?', 'Price is slightly negotiable.', 'Let me share the location pin.']) as $q): ?>
            <button type="button" class="vk-chip shrink-0 !text-xs" data-quick="<?= e($q) ?>"><?= e($q) ?></button>
          <?php endforeach; ?>
        </div>
        <form id="composer" class="p-3 sm:p-4 flex items-end gap-2">
          <textarea id="body" rows="1" maxlength="2000" class="vk-input resize-none max-h-40 !rounded-2xl" placeholder="Write a message… (links are fine, no attachments)"></textarea>
          <button class="vk-btn-accent !rounded-2xl !h-12 !w-12 !p-0 shrink-0" aria-label="Send"><i class="fa-solid fa-paper-plane"></i></button>
        </form>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php ob_start(); ?>
<script>
(function () {
  const ACTIVE = <?= json_encode($active['uuid'] ?? null) ?>;
  let filter = 'all', search = '', lastId = 0, lastDate = null, sending = false;
  const esc = s => $('<div>').text(s).html();

  /* ---- list ---- */
  function loadList() {
    $.get(VK.url('api/chats.php'), { action: 'list', filter, q: search }).then(r => {
      if (!r.items.length) { $('#chat-list').html('<li class="p-8 text-center text-muted"><i class="fa-regular fa-comment-dots text-3xl mb-2"></i><p>No conversations' + (filter !== 'all' || search ? ' match' : ' yet') + '.</p></li>'); return; }
      $('#chat-list').html(r.items.map(c => `
        <li><a href="${VK.url('chats.php', { c: c.id })}" class="flex gap-3 p-4 hover:bg-surface2 transition ${c.id === ACTIVE ? 'bg-accent/5 border-l-4 border-accent' : ''}">
          <span class="relative shrink-0"><img src="${c.thumb}" class="h-14 w-14 rounded-2xl object-cover" alt="">
            <span class="absolute -bottom-1 -right-1 grid h-6 w-6 place-items-center rounded-full bg-nav text-[10px] font-bold text-white ring-2 ring-surface">${esc(c.initials)}</span>
            ${c.online ? '<span class="absolute -top-0.5 -left-0.5 h-3 w-3 rounded-full bg-emerald-500 ring-2 ring-surface"></span>' : ''}</span>
          <span class="min-w-0 flex-1">
            <span class="flex items-center gap-2"><b class="truncate text-sm">${esc(c.other)}</b><span class="ml-auto text-[11px] text-muted shrink-0">${esc(c.when)}</span></span>
            <span class="block truncate text-xs font-semibold text-inksoft">${esc(c.listing_title)}</span>
            <span class="flex items-center gap-2"><span class="truncate text-xs ${c.unread ? 'text-ink font-bold' : 'text-muted'}">${esc(c.last)}</span>
              ${c.unread ? `<span class="ml-auto rounded-full bg-accent px-1.5 text-[10px] font-extrabold text-accentink">${c.unread}</span>` : `<span class="ml-auto text-[10px] font-bold uppercase ${c.role === 'buying' ? 'text-sky-500' : 'text-emerald-500'}">${c.role}</span>`}</span>
          </span></a></li>`).join(''));
    });
  }
  $('[data-chat-filter]').on('click', function () {
    filter = $(this).data('chat-filter');
    $('[data-chat-filter]').removeClass('!bg-nav !text-navink !border-nav'); $(this).addClass('!bg-nav !text-navink !border-nav'); loadList();
  });
  let t; $('#chat-search').on('input', function () { clearTimeout(t); search = this.value; t = setTimeout(loadList, 300); });
  loadList(); setInterval(() => { if (!document.hidden) loadList(); }, 12000);

  if (!ACTIVE) return;
  /* ---- thread ---- */
  const $m = $('#messages');
  function dayLabel(d) {
    const today = new Date().toISOString().slice(0, 10), y = new Date(Date.now() - 864e5).toISOString().slice(0, 10);
    return d === today ? 'Today' : d === y ? 'Yesterday' : new Date(d).toLocaleDateString('en-KE', { day: 'numeric', month: 'short', year: 'numeric' });
  }
  function render(msg) {
    if ($m.find('[data-mid="' + msg.id + '"]').length) return;
    if (msg.date !== lastDate) { $m.append(`<div class="text-center my-3"><span class="rounded-full bg-surface2 px-3 py-1 text-[11px] font-bold text-muted">${dayLabel(msg.date)}</span></div>`); lastDate = msg.date; }
    $m.append(`<div class="flex ${msg.mine ? 'justify-end' : 'justify-start'}" data-mid="${msg.id}"><div class="vk-bubble ${msg.mine ? 'me' : 'them'}">${msg.html}
      <span class="block text-[10px] mt-1 text-right opacity-70">${msg.time}${msg.mine ? ` <i class="fa-solid fa-check-double ${msg.read ? 'text-sky-200' : ''}" data-tick></i>` : ''}</span></div></div>`);
    lastId = Math.max(lastId, msg.id);
  }
  function poll(first) {
    return $.get(VK.url('api/chats.php'), { action: 'messages', c: ACTIVE, after: lastId }).then(r => {
      const nearBottom = $m[0].scrollHeight - $m.scrollTop() - $m.outerHeight() < 120;
      r.items.forEach(render);
      if (first && !r.items.length) $m.html('<div class="m-auto text-center text-muted py-10"><i class="fa-regular fa-hand text-3xl"></i><p class="mt-2">Say hello 👋 — ask about availability or book a viewing.</p></div>');
      if (r.items.length && (first || nearBottom)) $m.scrollTop($m[0].scrollHeight);
      $('#presence').html(r.other_online ? '<span class="inline-block h-2 w-2 rounded-full bg-emerald-500 mr-1"></span>Online' : (r.other_seen ? 'Last seen ' + r.other_seen : ''));
      $m.find('[data-mid]').each(function () { if (Number($(this).data('mid')) <= r.read_upto) $(this).find('[data-tick]').addClass('text-sky-200'); });
      if (r.items.length) VK.refreshCounts();
    });
  }
  poll(true); setInterval(() => { if (!document.hidden) poll(false); }, 4000);

  function send(text) {
    text = (text || '').trim(); if (!text || sending) return;
    sending = true;
    if ($m.find('.fa-hand').length) $m.empty();
    VK.api('chats.php', { action: 'send', c: ACTIVE, body: text }).then(r => {
      if (!$m.find('[data-mid="' + r.message.id + '"]').length) render(r.message);
      $m.scrollTop($m[0].scrollHeight); $('#body').val('').trigger('input'); loadList();
    }).always(() => sending = false);
  }
  $('#composer').on('submit', e => { e.preventDefault(); send($('#body').val()); });
  $('#body').on('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(this.value); } })
    .on('input', function () { this.style.height = 'auto'; this.style.height = Math.min(this.scrollHeight, 160) + 'px'; });
  $('[data-quick]').on('click', function () { send($(this).data('quick')); });
})();
</script>
<?php $extraScripts = ob_get_clean(); include APP_ROOT . '/includes/layout/footer.php'; ?>
