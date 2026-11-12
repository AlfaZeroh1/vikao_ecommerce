/* Shop page: live filtering, URL sync, chips, family-aware filter groups. */
(function ($) {
  'use strict';
  const $form = $('#filters'), $results = $('#results');
  let layout = 'grid', timer = null, xhr = null;
  try { layout = localStorage.getItem('vk-layout') || 'grid'; } catch (e) {}

  /* ---------- family / deal aware sections ---------- */
  function current() {
    const $o = $('#f-cat option:selected');
    return { family: $o.data('family') || '', deal: $o.data('deal') || '' };
  }
  function applyFamily(clear) {
    const { family, deal } = current();
    $form.find('[data-families]').each(function () {
      const fams = String($(this).data('families')).split(' ');
      let show = family && fams.includes(family);
      const deals = $(this).data('deals');
      if (show && deals) show = !!deal && String(deals).split(' ').includes(deal);
      $(this).toggle(show);
      $(this).find('input, select').prop('disabled', !show);
      if (!show && clear) $(this).find('input[type=checkbox], input[type=radio]').prop('checked', false).end().find('input[type=text], input:not([type])').val('');
    });
    // re-disable empty location children so they don't serialize noise
    $('#f-sub, #f-area').each(function () { if (!$(this).find('option').length || $(this).find('option').length < 2) $(this).prop('disabled', true); });
    renderPresets(deal, family);
  }

  /* ---------- price presets ---------- */
  const PRESETS = {
    sale: [[0, 5e6, '< 5M'], [5e6, 15e6, '5M – 15M'], [15e6, 50e6, '15M – 50M'], [50e6, '', '50M +']],
    rent: [[0, 20000, '< 20K'], [20000, 50000, '20K – 50K'], [50000, 150000, '50K – 150K'], [150000, '', '150K +']],
    short_stay: [[0, 5000, '< 5K'], [5000, 10000, '5K – 10K'], [10000, 25000, '10K – 25K'], [25000, '', '25K +']],
    service: [[0, 2000, '< 2K'], [2000, 10000, '2K – 10K'], [10000, '', '10K +']]
  };
  function renderPresets(deal) {
    const list = PRESETS[deal] || PRESETS.sale;
    $('#price-presets').html(list.map(p => `<button type="button" class="vk-chip" data-set='{"min_price":"${p[0] || ''}","max_price":"${p[1]}"}'>${p[2]}</button>`).join(''));
  }
  $(document).on('click', '[data-set]', function () {
    $.each($(this).data('set'), (k, v) => $form.find('[name="' + k + '"]').val(v));
    trigger();
  });

  /* ---------- fetch ---------- */
  function params() {
    const arr = $form.serializeArray().filter(p => p.value !== '' && !(p.name === 'sort' && p.value === 'recommended') && !(p.name === 'page' && p.value === '1'));
    return $.param(arr);
  }
  function fetchResults(push = true) {
    const qs = params();
    if (xhr) xhr.abort();
    $results.addClass('opacity-50 transition');
    xhr = $.get(VK.url('api/listings.php') + '?action=search&layout=' + layout + (qs ? '&' + qs : ''));
    xhr.then(function (r) {
      $results.html(r.html).removeClass('opacity-50');
      applyLayout();
      $('#no-results').toggleClass('hidden', r.total > 0);
      $('#result-count, #result-count-m').text(Number(r.total).toLocaleString());
      $('#pagination').html(r.pagination);
      $form.find('[name=page]').val(r.page);
      if (push) history.pushState({ qs }, '', VK.url('shop.php') + (qs ? '?' + qs : ''));
      updateHeader(); renderChips();
    }).catch(function (x) { if (x.statusText !== 'abort') { $results.removeClass('opacity-50'); VK.toast('Could not load results', 'error'); } });
  }
  function trigger(debounce) {
    $form.find('[name=page]').val(1);
    clearTimeout(timer);
    timer = setTimeout(fetchResults, debounce ? 400 : 10);
  }

  $form.on('change', '[data-filter]:not([data-debounce])', function () {
    if (this.id === 'f-cat') applyFamily(true);
    trigger(false);
  });
  $form.on('input', '[data-debounce]', () => trigger(true));
  $form.on('submit', e => { e.preventDefault(); trigger(false); });
  $(document).on('change', 'select[form=filters]', () => trigger(false));

  $(document).on('click', '#pagination [data-page]', function (e) {
    e.preventDefault();
    $form.find('[name=page]').val($(this).data('page'));
    fetchResults(); window.scrollTo({ top: 0, behavior: 'smooth' });
  });
  window.addEventListener('popstate', () => location.reload());

  /* ---------- header + chips ---------- */
  function updateHeader() {
    const $o = $('#f-cat option:selected'), q = $form.find('[name=q]').val();
    let t = $o.val() ? $o.text().replace(/^All /, '').replace(/^./, c => c.toUpperCase()) : (q ? 'Results for “' + q + '”' : ($form.find('[name=official]').is(':checked') ? 'Official Vikao Store' : 'All listings'));
    $('#head-title').text(t); document.title = t + ' · Vikao';
  }
  function labelFor($el) {
    if ($el.is('select')) return $el.find('option:selected').text();
    const $l = $el.closest('label');
    return ($l.text() || $el.attr('placeholder') || $el.attr('name')).trim();
  }
  function renderChips() {
    const chips = []; let total = 0;
    $form.find('details').each(function () {
      let n = 0;
      $(this).find('input:checked:enabled, input[type=text]:enabled, input:not([type]):enabled, input[inputmode]:enabled, select:enabled').each(function () {
        if (!$(this).val() || this.name === 'page') return;
        if (this.type === 'radio' && $(this).val() === '') return;
        n++;
      });
      $(this).find('[data-section-count]').text(n).toggleClass('hidden', !n);
      total += n;
    });
    $form.find('input:checked:enabled, select:enabled, input[type=text]:enabled, input[inputmode]:enabled, input[name=q]').each(function () {
      const v = $(this).val(); if (!v || this.name === 'page' || this.name === 'sort') return;
      let label = labelFor($(this));
      if (this.name === 'min_price') label = 'From KSh ' + Number(v).toLocaleString();
      if (this.name === 'max_price') label = 'Up to KSh ' + Number(v).toLocaleString();
      if (this.name === 'min_size') label = '≥ ' + v + ' m²'; if (this.name === 'max_size') label = '≤ ' + v + ' m²';
      if (this.name === 'min_acres') label = '≥ ' + v + ' acres'; if (this.name === 'max_acres') label = '≤ ' + v + ' acres';
      if (this.name === 'q') label = '“' + v + '”';
      if (this.name === 'baths') label = v + '+ baths';
      if (this.name === 'beds[]') label = (v === '0' ? 'Studio' : v + ' bed');
      if (this.name === 'guests') label = v + '+ guests';
      if (this.name === 'parking') label = v + '+ parking';
      chips.push(`<button type="button" class="vk-chip !bg-accent/10 !border-accent/40" data-chip-name="${this.name}" data-chip-value="${$('<i>').text(v).html()}">${$('<i>').text(label).html()}<i class="fa-solid fa-xmark !text-muted"></i></button>`);
    });
    if (chips.length) chips.push('<a href="' + VK.url('shop.php') + '" class="vk-chip !border-transparent text-accent">Clear all</a>');
    $('#active-chips').html(chips.join(''));
    $('#filter-count-m').text(total).toggleClass('hidden', !total);
  }
  $(document).on('click', '[data-chip-name]', function () {
    const name = $(this).data('chip-name'), val = String($(this).data('chip-value'));
    const $el = $form.find('[name="' + name + '"]').filter(function () { return String($(this).val()) === val || !this.type || this.type === 'text' || $(this).is('select') || $(this).attr('inputmode'); });
    $el.each(function () {
      if (this.type === 'checkbox' || this.type === 'radio') { if (String($(this).val()) === val) $(this).prop('checked', false); }
      else $(this).val('');
    });
    if (name === 'cat') applyFamily(true);
    if (name === 'county') { $('#f-sub, #f-area').html('<option value="">Any</option>').prop('disabled', true); }
    trigger(false);
  });

  /* ---------- layout + misc ---------- */
  function applyLayout() {
    $('[data-layout]').removeClass('is-active').filter('[data-layout="' + layout + '"]').addClass('is-active');
    $results.toggleClass('sm:grid-cols-2 xl:grid-cols-3', layout === 'grid').toggleClass('grid-cols-1', layout === 'list');
    $results.find('.vk-card').each(function () {
      $(this).toggleClass('sm:flex-row', layout === 'list');
      $(this).children('a').first().toggleClass('sm:w-72 sm:shrink-0 sm:aspect-auto', layout === 'list');
    });
  }
  $('[data-layout]').on('click', function () { layout = $(this).data('layout'); try { localStorage.setItem('vk-layout', layout); } catch (e) {} applyLayout(); });

  $(document).on('input', '[data-feature-search]', function () {
    const q = this.value.toLowerCase();
    $(this).siblings('[data-feature-list]').find('label').each(function () { $(this).toggle($(this).data('name').includes(q)); });
  });
  $(document).on('click', '[data-filters-open]', () => { $('#filter-drawer').removeClass('hidden'); $('body').addClass('overflow-hidden'); });
  $(document).on('click', '[data-filters-close]', () => { $('#filter-drawer').addClass('hidden lg:block'); $('body').removeClass('overflow-hidden'); });

  // init
  applyFamily(false); applyLayout(); renderChips();
  $(document).on('vk:loaded', '#f-sub, #f-area', renderChips);
})(jQuery);
