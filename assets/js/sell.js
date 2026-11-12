/* Sell / edit ad form */
(function ($) {
  'use strict';
  const C = window.VK_SELL, $form = $('#sell-form');
  let family = C.current ? C.current.family : null, deal = C.current ? C.current.deal : null;
  const newFiles = [];            // {id, file, url}
  const removed = [];             // existing media ids to delete
  let fileSeq = 0;
  const $grid = $('#photo-grid');
  let quill = null;

  /* ---------------- category picking ---------------- */
  function catsFor(fam) { return C.categories.filter(c => c.family === fam); }
  function setCategory(id) {
    const c = C.categories.find(x => x.id === Number(id));
    $('#category_id').val(c ? c.id : '');
    if (c) { family = c.family; deal = c.deal; }
    applyFamily(); refreshChecklist();
  }
  $('[data-family-pick]').on('click', function () {
    const fam = $(this).data('family-pick');
    $('[data-family-pick]').attr('data-on', 'false'); $(this).attr('data-on', 'true');
    family = fam; deal = null; $('#category_id').val('');
    $('#deal-pick, #service-pick').addClass('hidden');
    if (fam === 'service') { $('#service-pick').removeClass('hidden'); if ($('#service-cat').val()) setCategory($('#service-cat').val()); else applyFamily(); return; }
    const opts = catsFor(fam);
    if (opts.length === 1) { setCategory(opts[0].id); return; }
    $('#deal-options').html(opts.map(c => `<label class="vk-choice"><input type="radio" name="_deal" value="${c.id}"><span><i class="fa-solid ${c.deal === 'sale' ? 'fa-tag' : 'fa-key'}"></i>${c.deal === 'sale' ? 'For sale' : (fam === 'land' ? 'For lease' : 'For rent')}</span></label>`).join(''));
    $('#deal-pick').removeClass('hidden');
    applyFamily();
  });
  $(document).on('change', 'input[name=_deal]', function () { setCategory(this.value); });
  $('#service-cat').on('change', function () { setCategory(this.value); });

  function applyFamily() {
    const ready = !!$('#category_id').val();
    $('#form-body').toggleClass('hidden', !ready);
    $('[data-show-for]').each(function () {
      const show = !!family && String($(this).data('show-for')).split(' ').includes(family);
      $(this).toggle(show).find('input, select, textarea').prop('disabled', !show);
    });
    $('[data-hide-for]').each(function () {
      const hide = String($(this).data('hide-for')).split(' ').includes(family);
      $(this).toggle(!hide).find('input, select').prop('disabled', hide);
    });
    $('[data-deal-show]').each(function () {
      const show = !!deal && String($(this).data('deal-show')).split(' ').includes(deal);
      $(this).toggle(show).find('input').prop('disabled', !show);
    });
    // billing options per deal
    const allowed = deal === 'short_stay' ? ['night', 'week', 'month'] : ['hour', 'day', 'week', 'month', 'quarter', 'half_year', 'year'];
    $('[data-billing]').each(function () { const ok = allowed.includes($(this).data('billing')); $(this).toggle(ok).find('input').prop('disabled', !ok || !(deal === 'rent' || deal === 'short_stay')); });
    if (deal && !$('input[name=billing_period]:checked:enabled').length) $('input[name=billing_period][value=' + (deal === 'short_stay' ? 'night' : (family === 'land' ? 'year' : 'month')) + ']').prop('checked', true);
    // labels
    $('[data-price-label]').text(family === 'service' ? 'Starting price (KSh)' : (deal === 'sale' ? 'Price (KSh)' : (deal === 'short_stay' ? 'Rate (KSh)' : 'Rent (KSh)')));
    $('[data-price-req]').toggle(family !== 'service');
    $('[data-not-service]').toggle(family !== 'service');
    // pill defaults
    [['res_size_unit', 'sqm'], ['com_size_unit', 'sqm'], ['com_price_unit', 'entire'], ['land_price_unit', 'entire'], ['svc_pricing_model', 'quote'], ['svc_provider_type', 'individual']].forEach(([n, v]) => {
      if (!$('[name=' + n + ']:checked').length) $('[name=' + n + '][value=' + v + ']').prop('checked', true);
    });
    if (family === 'land' && !$('[name=land_size_unit]').val()) $('[name=land_size_unit]').val('acre');
    if (family === 'service' && !$('[name=listed_by]:checked').length) { /* service: listed_by defaults server-side */ }
  }

  /* ---------------- title / price helpers ---------------- */
  $('#title').on('input', function () { $('#title-count').text(this.value.length); refreshChecklist(); }).trigger('input');
  function words(n) {
    n = Number(String(n).replace(/[^\d.]/g, '')); if (!n) return '';
    if (n >= 1e9) return 'KSh ' + (n / 1e9).toFixed(2).replace(/\.?0+$/, '') + ' billion';
    if (n >= 1e6) return 'KSh ' + (n / 1e6).toFixed(2).replace(/\.?0+$/, '') + ' million';
    return 'KSh ' + n.toLocaleString('en-KE');
  }
  $('#price').on('input', function () {
    const raw = this.value.replace(/[^\d.]/g, ''); const pos = this.value.length;
    this.value = raw ? Number(raw.split('.')[0]).toLocaleString('en-KE') + (raw.includes('.') ? '.' + raw.split('.')[1] : '') : '';
    $('#price-words').text(words(raw)); refreshChecklist();
  }).trigger('input');
  $('#has_sc').on('change', function () { $('#sc-fields').toggleClass('hidden', !this.checked); });

  /* ---------------- Quill ---------------- */
  quill = new Quill('#editor', { theme: 'snow', placeholder: 'Describe the property: layout, finishes, nearby schools, roads, terms…',
    modules: { toolbar: [[{ header: [2, 3, false] }], ['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote', 'link'], ['clean']] } });
  quill.on('text-change', function () {
    const len = quill.getText().trim().length;
    if (len > C.descMax) quill.deleteText(C.descMax, quill.getLength());
    $('#desc-count').text(Math.min(len, C.descMax)).toggleClass('text-rose-500', len >= C.descMax);
    refreshChecklist();
  });
  $('#desc-count').text(quill.getText().trim().length);

  /* ---------------- YouTube preview ---------------- */
  function ytId(u) { const m = String(u || '').match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/); return m ? m[1] : null; }
  $('#youtube_url').on('input change', function () {
    const id = ytId(this.value);
    $('#yt-preview').toggleClass('hidden', !id).html(id ? `<iframe class="h-full w-full" src="https://www.youtube-nocookie.com/embed/${id}" allowfullscreen></iframe>` : '');
  }).trigger('change');

  /* ---------------- photos ---------------- */
  function photoCount() { return $grid.children('.vk-thumb').length; }
  function updatePhotos() {
    $('#photo-count').text(photoCount());
    $grid.find('.cover').remove();
    $grid.children('.vk-thumb').first().append('<span class="cover vk-badge bg-accent text-accentink"><i class="fa-solid fa-star"></i>Cover</span>');
    refreshChecklist();
  }
  function addFiles(list) {
    const errs = [];
    Array.from(list).forEach(f => {
      if (photoCount() >= C.maxPhotos) { errs.push('Maximum ' + C.maxPhotos + ' photos.'); return; }
      if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { errs.push(f.name + ': only JPG, PNG or WEBP'); return; }
      if (f.size > C.maxBytes) { errs.push(f.name + ' is larger than 5 MB'); return; }
      const id = 'n' + (++fileSeq), url = URL.createObjectURL(f);
      newFiles.push({ id, file: f, url });
      $grid.append(`<div class="vk-thumb" data-new="${id}"><img src="${url}" alt=""><button type="button" class="rm" aria-label="Remove"><i class="fa-solid fa-xmark"></i></button></div>`);
    });
    if (errs.length) VK.toast([...new Set(errs)].join(' · '), 'warning');
    updatePhotos();
  }
  $('#photo-browse, #dropzone').on('click', function (e) { if (e.target.closest('#photo-browse') || e.target.id === 'dropzone') $('#photo-input').trigger('click'); });
  $('#photo-input').on('change', function () { addFiles(this.files); this.value = ''; });
  $('#dropzone').on('dragover dragenter', e => { e.preventDefault(); $('#dropzone').addClass('is-drag'); })
    .on('dragleave drop', e => { e.preventDefault(); $('#dropzone').removeClass('is-drag'); })
    .on('drop', e => addFiles(e.originalEvent.dataTransfer.files));
  $grid.on('click', '.rm', function () {
    const $t = $(this).closest('.vk-thumb');
    if ($t.data('existing')) removed.push($t.data('existing'));
    if ($t.data('new')) { const i = newFiles.findIndex(x => x.id === $t.data('new')); if (i > -1) { URL.revokeObjectURL(newFiles[i].url); newFiles.splice(i, 1); } }
    $t.remove(); updatePhotos();
  });
  if (window.Sortable) Sortable.create($grid[0], { animation: 160, onEnd: updatePhotos });
  updatePhotos();

  /* ---------------- feature & area search ---------------- */
  $(document).on('input', '[data-feat-search]', function () {
    const q = this.value.toLowerCase();
    $(this).next('[data-feat-list]').find('[data-name]').each(function () { $(this).toggle($(this).data('name').includes(q)); });
  });
  $('#area-search').on('input', function () { const q = this.value.toLowerCase(); $('#area-list [data-name]').each(function () { $(this).toggle($(this).data('name').includes(q)); }); });

  /* ---------------- checklist ---------------- */
  function refreshChecklist() {
    const st = {
      category: !!$('#category_id').val(),
      title: $('#title').val().trim().length >= 8,
      location: !!$('[name=county_id]').val() && (family === 'service' || !!$('[name=subcounty_id]').val()),
      photos: photoCount() >= C.minPhotos,
      description: !!quill && quill.getText().trim().length >= 30,
      price: family === 'service' ? true : Number(String($('#price').val()).replace(/[^\d.]/g, '')) > 0,
    };
    let done = 0;
    $.each(st, (k, ok) => { if (ok) done++; $('[data-check=' + k + ']').toggleClass('text-emerald-600 dark:text-emerald-400 font-semibold', ok).toggleClass('text-muted', !ok).find('i').attr('class', ok ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle'); });
    $('#progress').css('width', Math.round(done / 6 * 100) + '%');
  }
  $form.on('change', '[name=county_id], [name=subcounty_id]', refreshChecklist);

  /* ---------------- submit ---------------- */
  $form.on('submit', function (e) {
    e.preventDefault();
    if (!$('#category_id').val()) { VK.showErrors($form, { category_id: 'Choose what you are listing.' }); return; }
    const html = quill.root.innerHTML;
    $('#description').val(quill.getText().trim().length ? html : '');
    const fd = new FormData(this);
    fd.set('price', String($('#price').val()).replace(/,/g, ''));
    // photos in on-screen order: existing ids order + new files appended in order shown
    $grid.children('.vk-thumb').each(function () {
      if ($(this).data('existing')) fd.append('media_order[]', $(this).data('existing'));
      if ($(this).data('new')) { const nf = newFiles.find(x => x.id === $(this).data('new')); if (nf) fd.append('photos[]', nf.file, nf.file.name); }
    });
    removed.forEach(id => fd.append('remove_media[]', id));
    const $b = $('#publish-btn'); VK.btnLoading($b, true, 'Uploading…');
    VK.api(C.endpoint || 'listings.php', fd, { silent: true, xhr: function () {
      const x = new window.XMLHttpRequest();
      x.upload.addEventListener('progress', ev => { if (ev.lengthComputable) $b.html('<i class="fa-solid fa-circle-notch fa-spin"></i> Uploading ' + Math.round(ev.loaded / ev.total * 100) + '%'); });
      return x;
    } }).then(function (r) {
      Swal.fire({ icon: 'success', title: 'Success!', html: r.message + (C.official ? '' : '<br><span class="text-sm opacity-75">You\'ll get a notification as soon as it\'s approved.</span>'), confirmButtonText: C.official ? 'View listing' : 'Go to my listings' })
        .then(() => location.href = r.redirect);
    }).catch(function (j) {
      VK.btnLoading($b, false);
      VK.showErrors($form, j.errors || {});
      VK.toast(j.message || 'Please fix the highlighted fields.', 'error');
    });
  });

  /* ---------------- official packages ---------------- */
  function addPackage(p) {
    const $p = $($('#package-tpl').html());
    $.each(p || {}, (k, v) => { const $i = $p.find('[data-k="' + k + '"]'); if ($i.is(':checkbox')) $i.prop('checked', !!Number(v)); else $i.val(v === null ? '' : (typeof v === 'string' && /^\d+\.00$/.test(v) ? parseInt(v, 10) : v)); });
    $('#packages').append($p); renumber();
  }
  function renumber() {
    $('#packages [data-package]').each(function (i) { $(this).find('[data-k]').each(function () { this.name = 'packages[' + i + '][' + $(this).data('k') + ']'; }); });
  }
  if (C.official) {
    (C.packages && C.packages.length ? C.packages : [{ name: 'Outright purchase', package_type: 'outright', is_recommended: 1 }]).forEach(addPackage);
    $('#add-package').on('click', () => addPackage({}));
    $('#packages').on('click', '[data-remove-package]', function () { $(this).closest('[data-package]').remove(); renumber(); });
  }

  // init
  if (C.current) {
    $('[data-family-pick=' + C.current.family + ']').attr('data-on', 'true');
    if (C.current.family === 'service') $('#service-pick').removeClass('hidden');
    else if (catsFor(C.current.family).length > 1) {
      $('[data-family-pick=' + C.current.family + ']').trigger('click');
      $('input[name=_deal][value=' + C.current.id + ']').prop('checked', true).trigger('change');
    }
  }
  applyFamily(); refreshChecklist();
})(jQuery);
