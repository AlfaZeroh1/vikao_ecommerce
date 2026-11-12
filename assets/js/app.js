/* =====================================================================
   Vikao — global UI (jQuery). Loaded on every page.
   ===================================================================== */
window.VK = (function ($) {
  'use strict';
  const base = $('meta[name="base-url"]').attr('content') || '';
  const csrf = $('meta[name="csrf-token"]').attr('content');
  const url = (p, q) => base + '/' + String(p || '').replace(/^\//, '') + (q ? '?' + $.param(q) : '');

  $.ajaxSetup({ headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' }, dataType: 'json' });

  /* ---------------- toasts & dialogs ---------------- */
  const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3200, timerProgressBar: true, customClass: { popup: 'shadow-lift' } });
  const toast = (msg, icon = 'success') => Toast.fire({ icon, title: msg });
  const confirm = (opts) => Swal.fire(Object.assign({ icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, continue', cancelButtonText: 'Cancel', reverseButtons: true, focusCancel: true }, opts)).then(r => r.isConfirmed ? r : null);

  /** POST helper: returns promise of json; handles 401 + errors uniformly */
  function api(endpoint, data, opts = {}) {
    const isForm = data instanceof FormData;
    return $.ajax(Object.assign({
      url: url('api/' + endpoint), method: opts.method || 'POST', data: data,
      processData: !isForm, contentType: isForm ? false : 'application/x-www-form-urlencoded; charset=UTF-8'
    }, opts)).catch(function (xhr) {
      const j = xhr.responseJSON || {};
      if (xhr.status === 401) {
        Swal.fire({ icon: 'info', title: 'Sign in required', text: j.message || 'Please sign in to continue.', confirmButtonText: 'Sign in', showCancelButton: true })
          .then(r => { if (r.isConfirmed) location.href = url('login.php', { next: location.pathname + location.search }); });
      } else if (!opts.silent) {
        toast(j.message || 'Something went wrong. Please try again.', 'error');
      }
      return $.Deferred().reject(j, xhr).promise();
    });
  }

  /** Show field errors from {errors:{field:msg}} under inputs with name=field */
  function showErrors($form, errors) {
    $form.find('.vk-error').remove();
    $form.find('.is-invalid').removeClass('is-invalid');
    let first = null;
    $.each(errors || {}, function (field, msg) {
      let $f = $form.find('[name="' + field + '"], [name="' + field + '[]"], [data-field="' + field + '"]').first();
      if (!$f.length) return;
      const $wrap = $f.closest('[data-field-wrap]').length ? $f.closest('[data-field-wrap]') : $f.parent();
      $f.addClass('is-invalid');
      $wrap.append('<p class="vk-error"><i class="fa-solid fa-circle-exclamation"></i> ' + $('<div>').text(msg).html() + '</p>');
      if (!first) first = $wrap;
    });
    if (first && first[0]) first[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function btnLoading($btn, on, text) {
    if (on) { $btn.data('html', $btn.html()).addClass('is-loading').html('<i class="fa-solid fa-circle-notch fa-spin"></i> ' + (text || 'Please wait…')); }
    else { $btn.removeClass('is-loading').html($btn.data('html')); }
  }

  /* ---------------- theme ---------------- */
  $(document).on('click', '[data-theme-toggle]', function () {
    const dark = !document.documentElement.classList.contains('dark');
    document.documentElement.classList.toggle('dark', dark);
    try { localStorage.setItem('vk-theme', dark ? 'dark' : 'light'); } catch (e) {}
    $.post(url('api/profile.php'), { action: 'theme', theme: dark ? 'dark' : 'light' }).catch(() => {});
  });

  /* ---------------- navbar dropdowns ---------------- */
  const closeAll = () => $('[data-dd].is-open').removeClass('is-open').find('[data-dd-toggle]').attr('aria-expanded', 'false');
  $(document).on('click', '[data-dd-toggle]', function (e) {
    e.stopPropagation();
    const $dd = $(this).closest('[data-dd]'); const open = $dd.hasClass('is-open');
    closeAll(); if (!open) { $dd.addClass('is-open'); $(this).attr('aria-expanded', 'true'); }
  });
  if (window.matchMedia('(hover: hover) and (min-width: 768px)').matches) {
    let t;
    $(document).on('mouseenter', '[data-dd]', function () { clearTimeout(t); closeAll(); $(this).addClass('is-open'); })
               .on('mouseleave', '[data-dd]', function () { const $d = $(this); t = setTimeout(() => $d.removeClass('is-open'), 140); });
  }
  $(document).on('click', function (e) { if (!$(e.target).closest('[data-dd-panel]').length) closeAll(); });
  $(document).on('keydown', function (e) { if (e.key === 'Escape') { closeAll(); closeSearch(); } });

  /* ---------------- search overlay ---------------- */
  function closeSearch() { $('#vk-search').addClass('hidden'); $('body').removeClass('overflow-hidden'); }
  $(document).on('click', '[data-search-open]', function () { $('#vk-search').removeClass('hidden'); $('body').addClass('overflow-hidden'); setTimeout(() => $('#vk-search input[name=q]').trigger('focus'), 30); });
  $(document).on('click', '[data-search-close]', closeSearch);

  /* ---------------- favourites ---------------- */
  $(document).on('click', '[data-fav]', function (e) {
    e.preventDefault();
    const $b = $(this), id = $b.data('fav');
    api('favourites.php', { action: 'toggle', listing: id }).then(function (r) {
      $('[data-fav="' + id + '"]').toggleClass('is-fav', r.favourited).attr('aria-pressed', r.favourited)
        .find('i').toggleClass('fa-solid', r.favourited).toggleClass('fa-regular', !r.favourited);
      $b.addClass('pop'); setTimeout(() => $b.removeClass('pop'), 400);
      toast(r.favourited ? 'Saved to favourites' : 'Removed from favourites', r.favourited ? 'success' : 'info');
      $(document).trigger('vk:fav', [id, r.favourited]);
      refreshCounts();
    });
  });

  /* ---------------- badge counts (polling) ---------------- */
  function setBadge(key, n) {
    $('[data-count="' + key + '"]').each(function () { $(this).text(n > 99 ? '99+' : n).prop('hidden', !n); });
  }
  function refreshCounts() {
    if (!$('[data-count]').length) return;
    $.get(url('api/notifications.php'), { action: 'counts' }).then(function (r) {
      if (!r || !r.ok) return; setBadge('notif', r.notifications); setBadge('chats', r.chats);
    }).catch(() => {});
  }
  refreshCounts(); setInterval(() => { if (!document.hidden) refreshCounts(); }, 30000);

  /* ---------------- flash messages from PHP ---------------- */
  $(function () {
    $('.vk-flash').each(function () {
      const t = $(this).data('type'); toast($(this).data('msg'), t === 'error' ? 'error' : (t === 'info' ? 'info' : 'success'));
    });
  });

  /* ---------------- location cascade (county > sub-county > area) ----------------
     Markup: <select data-loc-level="1" data-loc-target="#sub">, <select id="sub" data-loc-level="2" data-loc-target="#area"> */
  function loadChildren($child, parentId, selected) {
    const ph = $child.data('placeholder') || 'Any';
    $child.html('<option value="">' + ph + '</option>').prop('disabled', !parentId);
    const $next = $($child.data('loc-target')); if ($next.length) { $next.html('<option value="">' + ($next.data('placeholder') || 'Any') + '</option>').prop('disabled', true); }
    if (!parentId) return $.Deferred().resolve().promise();
    return $.get(url('api/locations.php'), { action: 'children', parent: parentId }).then(function (r) {
      (r.items || []).forEach(function (it) { $child.append($('<option>').val(it.id).text(it.name)); });
      $child.prop('disabled', !(r.items || []).length);
      if (selected) $child.val(String(selected));
    });
  }
  $(document).on('change', '[data-loc-target]', function () {
    const $t = $($(this).data('loc-target')); loadChildren($t, $(this).val()).then(() => $t.trigger('vk:loaded'));
  });
  /** init with preselected values: data-selected on each select */
  function initLocations(scope) {
    $(scope || document).find('[data-loc-level="1"]').each(function () {
      const $c = $(this), $s = $($c.data('loc-target')), $a = $($s.data('loc-target'));
      if ($c.val() && $s.length) loadChildren($s, $c.val(), $s.data('selected')).then(function () {
        if ($s.val() && $a.length) loadChildren($a, $s.val(), $a.data('selected'));
      });
    });
  }
  $(function () { initLocations(); });

  /* ---------------- misc ---------------- */
  $(document).on('click', '[data-copy]', function () {
    const v = $(this).data('copy'); navigator.clipboard && navigator.clipboard.writeText(v).then(() => toast('Copied to clipboard'));
  });
  $(document).on('click', '[data-share]', function () {
    const data = { title: document.title, url: location.href };
    if (navigator.share) navigator.share(data).catch(() => {}); else navigator.clipboard.writeText(location.href).then(() => toast('Link copied'));
  });
  const fmt = (n) => Number(n || 0).toLocaleString('en-KE');
  const money = (n, c) => (c === 'KES' || !c ? 'KSh ' : c + ' ') + fmt(n);

  return { url, api, toast, confirm, showErrors, btnLoading, refreshCounts, initLocations, loadChildren, money, fmt };
})(jQuery);
