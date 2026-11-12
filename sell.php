<?php
if (!defined('APP_ROOT')) require __DIR__ . '/includes/bootstrap.php';
$officialMode = $officialMode ?? false;   // true when included from pms/post.php
$me = require_login();

/* ---------- edit mode prefill ---------- */
$edit = null; $v = []; $media = []; $featSel = []; $areasSel = [];
if (!empty($_GET['edit'])) {
    $row = find_listing((string)$_GET['edit']);
    if ($officialMode) {
        if (!$row || !$row['is_official']) { flash('error', 'Official listing not found.'); redirect('pms/post.php'); }
    } else {
        if (!$row || (int)$row['user_id'] !== (int)$me['id']) { flash('error', 'Listing not found.'); redirect('my-listings.php'); }
        if ($row['is_official']) { flash('error', 'Official listings are edited from the PMS.'); redirect('my-listings.php'); }
    }
    $edit = load_listing_full($row);
    $d = $edit['details'];
    $chain = $edit['location_chain'];
    $v = [
        'title' => $edit['title'], 'category_id' => $edit['category_id'], 'estate' => $edit['estate'], 'address' => $edit['address'],
        'county_id' => $chain[0]['id'] ?? '', 'subcounty_id' => $chain[1]['id'] ?? '', 'area_id' => $chain[2]['id'] ?? '',
        'youtube_url' => $edit['youtube_url'], 'price' => $edit['price'] !== null ? (float)$edit['price'] : '', 'is_negotiable' => $edit['is_negotiable'],
        'billing_period' => $edit['billing_period'], 'listed_by' => $edit['listed_by'],
        'has_service_charge' => $edit['has_service_charge'], 'service_charge_amount' => $edit['service_charge_amount'] !== null ? (float)$edit['service_charge_amount'] : '',
        'service_charge_period' => $edit['service_charge_period'], 'service_charge_notes' => $edit['service_charge_notes'],
        'com_price_unit' => $edit['price_unit'], 'land_price_unit' => $edit['price_unit'],
        'reservation_fee' => $edit['reservation_fee'] !== null ? (float)$edit['reservation_fee'] : '',
        'pms_property_id' => $edit['pms_property_id'], 'pms_unit_id' => $edit['pms_unit_id'],
    ];
    $prefix = ['residential' => 'res_', 'short_stay' => 'res_', 'commercial' => 'com_', 'land' => 'land_', 'service' => 'svc_'][$edit['family']];
    foreach ($d as $k => $val) { if ($k === 'listing_id') continue; $v[$prefix . $k] = is_string($val) && preg_match('/^\d+\.\d+$/', $val) ? (float)$val : $val; }
    if (!empty($v['res_check_in_time'])) $v['res_check_in_time'] = substr($v['res_check_in_time'], 0, 5);
    if (!empty($v['res_check_out_time'])) $v['res_check_out_time'] = substr($v['res_check_out_time'], 0, 5);
    $media = $edit['media'];
    $featSel = array_map('intval', array_column($edit['features'], 'id'));
    $areasSel = array_map('intval', array_column($edit['service_areas'], 'id'));
}
$val = fn($k, $d = '') => e($v[$k] ?? $d);
$sel = fn($k, $opt) => isset($v[$k]) && (string)$v[$k] === (string)$opt ? 'selected' : '';
$chk = fn($k, $opt = '1') => isset($v[$k]) && $v[$k] !== null && (string)$v[$k] === (string)$opt ? 'checked' : '';

$cats = postable_categories();
$curCat = isset($v['category_id']) ? (categories()[(int)$v['category_id']] ?? null) : null;
$counties = all("SELECT id, name FROM locations WHERE country_id = 1 AND level = 1 ORDER BY name");
$families = [
    'residential' => ['Residential', 'fa-house-chimney', 'Houses, apartments, rooms'],
    'commercial'  => ['Commercial', 'fa-building', 'Offices, shops, warehouses, halls'],
    'land'        => ['Land', 'fa-mountain-sun', 'Plots, farms, acreage'],
    'short_stay'  => ['Short Stay', 'fa-bed', 'Airbnbs, holiday homes'],
    'service'     => ['Services', 'fa-screwdriver-wrench', 'Your property-related trade'],
];
$minPhotos = (int)config('uploads.min_photos', 6);

$packages = $edit ? $edit['packages'] : [];
$pageTitle = $officialMode ? 'PMS · Official listing' : ($edit ? 'Edit ad' : 'Post an ad');
$extraHead = '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css">';
include APP_ROOT . '/includes/layout/header.php';
if ($officialMode) component('pms_nav', ['active' => 'post']);
component('page_header', $officialMode
    ? ['icon' => 'fa-solid fa-crown', 'title' => $edit ? 'Edit official listing' : 'Post to Official Vikao Store', 'subtitle' => 'Official listings publish immediately with the Official Vikao badge, a reservation fee and purchase / lease packages.']
    : ['icon' => 'fa-solid ' . ($edit ? 'fa-pen-to-square' : 'fa-plus'), 'title' => $edit ? 'Edit your ad' : 'Post an ad',
       'subtitle' => $edit ? 'Changes are reviewed before they go live again.' : 'It takes about 5 minutes. Every ad is reviewed by Vikao before it goes live.']);

/* small render helpers */
$sectionOpen = function (string $id, string $icon, string $title, string $sub = '', string $attrs = '') { echo "<section id=\"$id\" class=\"vk-panel p-5 sm:p-7 scroll-mt-32\" $attrs><div class=\"mb-5\"><h2 class=\"vk-section-title\"><i class=\"fa-solid $icon\"></i>" . e($title) . "</h2>" . ($sub ? '<p class="text-sm text-muted mt-1 ml-[2.6rem]">' . e($sub) . '</p>' : '') . '</div>'; };
$select = function (string $name, array $options, string $placeholder = 'Select', bool $req = false) use ($sel) {
    $h = '<select name="' . $name . '" class="vk-select"' . ($req ? ' data-required' : '') . '><option value="">' . e($placeholder) . '</option>';
    foreach ($options as $k => $o) $h .= '<option value="' . e($k) . '" ' . $sel($name, $k) . '>' . e($o) . '</option>';
    return $h . '</select>';
};
$pills = function (string $name, array $options, string $type = 'radio') use ($chk) {
    $h = '<div class="flex flex-wrap gap-2" data-field="' . $name . '">';
    foreach ($options as $k => $o) { [$label, $icon] = is_array($o) ? $o : [$o, null];
        $h .= '<label class="vk-choice"><input type="' . $type . '" name="' . $name . '" value="' . e($k) . '" ' . $chk($name, $k) . '><span>' . ($icon ? '<i class="fa-solid ' . $icon . '"></i>' : '') . e($label) . '</span></label>'; }
    return $h . '</div>';
};
$num = fn(string $name, string $ph = '', string $extra = '') => '<input name="' . $name . '" value="' . $val($name) . '" inputmode="numeric" class="vk-input" placeholder="' . e($ph) . '" ' . $extra . '>';
$field = fn(string $label, string $control, bool $req = false, string $hint = '', string $cls = '') => '<div data-field-wrap class="' . $cls . '"><label class="vk-label">' . e($label) . ($req ? ' <span class="req">*</span>' : '') . '</label>' . $control . ($hint ? '<p class="mt-1 text-xs text-muted">' . $hint . '</p>' : '') . '</div>';
?>
<form id="sell-form" class="mx-auto max-w-7xl px-4 sm:px-6 grid lg:grid-cols-[1fr_300px] gap-6 items-start" novalidate enctype="multipart/form-data">
  <input type="hidden" name="action" value="<?= $officialMode ? 'post_official' : 'save' ?>">
  <?php if ($edit): ?><input type="hidden" name="listing" value="<?= e($edit['uuid']) ?>"><?php endif; ?>
  <input type="hidden" name="category_id" id="category_id" value="<?= $val('category_id') ?>">
  <input type="hidden" name="description" id="description">

  <div class="space-y-6 min-w-0">
    <!-- 1. CATEGORY -->
    <?php $sectionOpen('s-category', 'fa-layer-group', 'What are you listing?', 'Pick a category — the form adapts to it.'); ?>
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3" data-field="category_id" data-field-wrap>
        <?php foreach ($families as $fk => [$fl, $fi, $fd]): ?>
          <button type="button" class="group rounded-2xl border-2 border-line p-4 text-left hover:border-accent transition data-[on=true]:border-accent data-[on=true]:bg-accent/5" data-family-pick="<?= $fk ?>" data-on="<?= $curCat && $curCat['family'] === $fk ? 'true' : 'false' ?>">
            <span class="grid h-11 w-11 place-items-center rounded-xl bg-surface2 text-accent text-lg group-data-[on=true]:bg-accent group-data-[on=true]:text-accentink transition"><i class="fa-solid <?= $fi ?>"></i></span>
            <span class="mt-3 block font-bold"><?= e($fl) ?></span><span class="block text-xs text-muted leading-snug"><?= e($fd) ?></span>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="mt-4 hidden" id="deal-pick">
        <p class="vk-label">Are you selling or letting?</p>
        <div class="flex flex-wrap gap-2" id="deal-options"></div>
      </div>
      <div class="mt-4 hidden" id="service-pick" data-field-wrap>
        <label class="vk-label" for="service-cat">Type of service <span class="req">*</span></label>
        <div class="vk-input-group"><i class="fa-solid fa-screwdriver-wrench"></i>
          <select id="service-cat" class="vk-select"><option value="">Choose the service you offer</option>
            <?php foreach (service_categories() as $s): ?><option value="<?= $s['id'] ?>" <?= $curCat && (int)$curCat['id'] === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
          </select></div>
      </div>
    </section>

    <div id="form-body" class="space-y-6 <?= $curCat ? '' : 'hidden' ?>">
    <!-- 2. BASICS -->
    <?php $sectionOpen('s-basics', 'fa-pen-nib', 'Title & location'); ?>
      <div class="space-y-4">
        <div data-field-wrap>
          <label class="vk-label" for="title">Ad title <span class="req">*</span></label>
          <input id="title" name="title" value="<?= $val('title') ?>" maxlength="<?= (int)config('listing.title_max') ?>" class="vk-input" placeholder="e.g. Spacious 3 Bedroom Apartment in Kilimani with Pool">
          <p class="mt-1 text-xs text-muted flex justify-between"><span>Be specific: type, size and best feature.</span><span><span id="title-count">0</span>/<?= (int)config('listing.title_max') ?></span></p>
        </div>
        <div class="grid sm:grid-cols-3 gap-3">
          <div data-field-wrap><label class="vk-label">County <span class="req">*</span></label>
            <select name="county_id" class="vk-select" data-loc-level="1" data-loc-target="#subcounty_id"><option value="">Select county</option>
              <?php foreach ($counties as $c): ?><option value="<?= $c['id'] ?>" <?= $sel('county_id', $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
          <div data-field-wrap><label class="vk-label">Sub-county <span class="req" data-not-service>*</span></label>
            <select name="subcounty_id" id="subcounty_id" class="vk-select" data-loc-level="2" data-loc-target="#area_id" data-placeholder="Select sub-county" data-selected="<?= $val('subcounty_id') ?>" disabled><option value="">Select sub-county</option></select></div>
          <div data-field-wrap><label class="vk-label">Area / estate</label>
            <select name="area_id" id="area_id" class="vk-select" data-loc-level="3" data-placeholder="Optional" data-selected="<?= $val('area_id') ?>" disabled><option value="">Optional</option></select></div>
        </div>
        <div class="grid sm:grid-cols-2 gap-3" data-show-for="residential commercial land short_stay">
          <?= $field('Estate / building name', '<input name="estate" value="' . $val('estate') . '" class="vk-input" placeholder="e.g. Lenana Towers">') ?>
          <?= $field('Street address / landmark', '<input name="address" value="' . $val('address') . '" class="vk-input" placeholder="e.g. Off Argwings Kodhek Rd">') ?>
        </div>
      </div>
    </section>

    <!-- 3. PHOTOS -->
    <?php $sectionOpen('s-photos', 'fa-images', 'Photos', "Add at least $minPhotos photos · JPG, PNG or WEBP · max 5 MB each. Drag to reorder — the first photo is the cover."); ?>
      <div class="vk-dropzone p-6 text-center" id="dropzone" data-field="photos" data-field-wrap>
        <input type="file" id="photo-input" accept="image/jpeg,image/png,image/webp" multiple class="hidden">
        <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-accent/10 text-accent text-2xl"><i class="fa-solid fa-cloud-arrow-up"></i></div>
        <p class="mt-3 font-bold">Drag & drop photos here</p>
        <p class="text-sm text-muted">or <button type="button" class="font-bold text-accent" id="photo-browse">browse your device</button></p>
        <div class="mt-4 inline-flex items-center gap-2 rounded-full bg-surface px-3 py-1 text-xs font-bold"><i class="fa-solid fa-camera text-accent"></i><span id="photo-count">0</span> / min <?= $minPhotos ?></div>
      </div>
      <div id="photo-grid" class="mt-4 grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3">
        <?php foreach ($media as $i => $m): ?>
          <div class="vk-thumb" data-existing="<?= (int)$m['id'] ?>"><img src="<?= e(media_url($m['thumb_path'])) ?>" alt=""><button type="button" class="rm" aria-label="Remove"><i class="fa-solid fa-xmark"></i></button></div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- 4. DESCRIPTION -->
    <?php $sectionOpen('s-description', 'fa-align-left', 'Description & 360° tour'); ?>
      <div data-field="description" data-field-wrap>
        <label class="vk-label">Description <span class="req">*</span></label>
        <div id="editor"><?= $edit ? $edit['description_html'] : '' ?></div>
        <p class="mt-1 text-xs text-muted flex justify-between"><span>Mention size, nearby amenities, access roads, terms…</span><span><span id="desc-count">0</span>/<?= (int)config('listing.description_max') ?></span></p>
      </div>
      <div class="mt-5" data-field-wrap>
        <label class="vk-label" for="youtube_url"><i class="fa-brands fa-youtube text-rose-600"></i> YouTube 360° video link</label>
        <div class="vk-input-group"><i class="fa-solid fa-vr-cardboard"></i><input id="youtube_url" name="youtube_url" value="<?= $val('youtube_url') ?>" class="vk-input" placeholder="https://youtube.com/watch?v=…"></div>
        <p class="mt-1 text-xs text-muted">Upload your 360° walkthrough to YouTube (unlisted is fine) and paste the link.</p>
        <div id="yt-preview" class="mt-3 hidden aspect-video max-w-md overflow-hidden rounded-2xl bg-black"></div>
      </div>
    </section>

    <!-- 5a. RESIDENTIAL / SHORT STAY DETAILS -->
    <?php $sectionOpen('s-res', 'fa-house-chimney', 'Property details', '', 'data-show-for="residential short_stay"'); ?>
      <div class="space-y-5">
        <div data-field-wrap data-field="res_property_type_id"><label class="vk-label">Property type <span class="req">*</span></label>
          <div class="flex flex-wrap gap-2">
            <?php foreach (property_types('residential') as $t): ?><label class="vk-choice"><input type="radio" name="res_property_type_id" value="<?= $t['id'] ?>" <?= $chk('res_property_type_id', $t['id']) ?>><span><i class="fa-solid <?= e($t['icon']) ?>"></i><?= e($t['name']) ?></span></label><?php endforeach; ?>
          </div></div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <?= $field('Bedrooms', $num('res_bedrooms', '0 = studio', 'min="0" max="50"'), true) ?>
          <?= $field('Bathrooms', $num('res_bathrooms', 'e.g. 2', 'min="0" max="50"'), true) ?>
          <?= $field('Toilets', $num('res_toilets', 'optional')) ?>
          <?= $field('Parking spaces', $num('res_parking_spaces', 'optional')) ?>
        </div>
        <div class="grid sm:grid-cols-[1fr_auto_1fr] gap-3 items-end">
          <?= $field('Size', $num('res_size_value', 'e.g. 120')) ?>
          <div><?= $pills('res_size_unit', ['sqm' => 'm²', 'sqft' => 'ft²']) ?></div>
          <?= $field('Floor number', $num('res_floor_number', 'optional')) ?>
        </div>
        <div data-hide-for="short_stay" class="space-y-5">
          <?= $field('Self contained', $pills('res_self_contained', ['1' => ['Yes', 'fa-check'], '0' => ['No', 'fa-xmark']])) ?>
          <?= $field('Condition', $pills('res_condition', ['new' => ['Newly built', 'fa-wand-magic-sparkles'], 'used' => ['Used', 'fa-house'], 'renovated' => ['Renovated', 'fa-paint-roller'], 'off_plan' => ['Off-plan', 'fa-compass-drafting']]), true) ?>
          <?= $field('Furnishing', $pills('res_furnishing', ['furnished' => ['Fully furnished', 'fa-couch'], 'semi_furnished' => ['Semi furnished', 'fa-chair'], 'unfurnished' => ['Unfurnished', 'fa-box-open']]), true) ?>
          <?= $field('Pets allowed', $pills('res_pets_allowed', ['1' => ['Yes', 'fa-paw'], '0' => ['No', 'fa-ban']])) ?>
        </div>
        <div data-show-for="short_stay" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <?= $field('Max guests', $num('res_max_guests', 'e.g. 4'), true) ?>
          <?= $field('Beds', $num('res_beds', 'e.g. 2')) ?>
          <?= $field('Minimum nights', $num('res_min_nights', 'e.g. 1')) ?>
          <?= $field('Check-in from', '<input type="time" name="res_check_in_time" value="' . $val('res_check_in_time', '14:00') . '" class="vk-input">') ?>
          <?= $field('Check-out by', '<input type="time" name="res_check_out_time" value="' . $val('res_check_out_time', '10:00') . '" class="vk-input">') ?>
          <?= $field('Cleaning fee (KSh)', $num('res_cleaning_fee', 'optional')) ?>
          <?= $field('Security deposit (KSh)', $num('res_security_deposit', 'optional')) ?>
        </div>
      </div>
    </section>

    <!-- 5b. COMMERCIAL -->
    <?php $sectionOpen('s-com', 'fa-building', 'Commercial details', '', 'data-show-for="commercial"'); ?>
      <div class="space-y-5">
        <div data-field-wrap data-field="com_property_type_id"><label class="vk-label">Property type <span class="req">*</span></label>
          <div class="flex flex-wrap gap-2 max-h-72 overflow-y-auto pr-1">
            <?php foreach (property_types('commercial') as $t): ?><label class="vk-choice"><input type="radio" name="com_property_type_id" value="<?= $t['id'] ?>" <?= $chk('com_property_type_id', $t['id']) ?>><span><i class="fa-solid <?= e($t['icon']) ?>"></i><?= e($t['name']) ?></span></label><?php endforeach; ?>
          </div></div>
        <div class="grid sm:grid-cols-[1fr_auto_1fr] gap-3 items-end">
          <?= $field('Size', $num('com_size_value', 'e.g. 2,400'), true) ?>
          <div><?= $pills('com_size_unit', ['sqm' => 'm²', 'sqft' => 'ft²']) ?></div>
          <?= $field('No. of parking spaces', $num('com_parking_spaces', 'e.g. 4')) ?>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <?= $field('Floor', $num('com_floor_number', 'optional')) ?>
          <?= $field('Total floors', $num('com_total_floors', 'optional')) ?>
          <?= $field('Capacity (people)', $num('com_capacity_people', 'halls, venues')) ?>
          <?= $field('Washrooms', $num('com_washrooms', 'optional')) ?>
        </div>
        <?= $field('Condition', $pills('com_condition', ['new' => ['New', 'fa-wand-magic-sparkles'], 'used' => ['Used', 'fa-building'], 'renovated' => ['Renovated', 'fa-paint-roller'], 'off_plan' => ['Off-plan', 'fa-compass-drafting']]), true) ?>
        <?= $field('Furnishing', $pills('com_furnishing', ['furnished' => ['Fitted / furnished', 'fa-couch'], 'semi_furnished' => ['Semi fitted', 'fa-chair'], 'unfurnished' => ['Shell / bare', 'fa-box-open']])) ?>
        <div class="grid sm:grid-cols-2 gap-3">
          <?= $field('Power supply', $select('com_power_phase', OPT['power_phase'], 'Select')) ?>
          <?= $field('Zoning', $select('com_zoning', OPT['commercial_zoning'], 'Select')) ?>
        </div>
      </div>
    </section>

    <!-- 5c. LAND -->
    <?php $sectionOpen('s-land', 'fa-mountain-sun', 'Land details', '', 'data-show-for="land"'); ?>
      <div class="space-y-5">
        <div data-field-wrap data-field="land_property_type_id"><label class="vk-label">Land type <span class="req">*</span></label>
          <div class="flex flex-wrap gap-2">
            <?php foreach (property_types('land') as $t): ?><label class="vk-choice"><input type="radio" name="land_property_type_id" value="<?= $t['id'] ?>" <?= $chk('land_property_type_id', $t['id']) ?>><span><i class="fa-solid <?= e($t['icon']) ?>"></i><?= e($t['name']) ?></span></label><?php endforeach; ?>
          </div></div>
        <div class="grid sm:grid-cols-2 gap-3">
          <?= $field('Land size', '<input name="land_size_value" value="' . $val('land_size_value') . '" inputmode="decimal" class="vk-input" placeholder="e.g. 0.5">', true) ?>
          <?= $field('Unit', $select('land_size_unit', OPT['land_size_unit'], 'Select unit')) ?>
        </div>
        <?= $field('Tenure', $pills('land_tenure', ['freehold' => ['Freehold', 'fa-infinity'], 'leasehold' => ['Leasehold', 'fa-hourglass-half'], 'community' => ['Community land', 'fa-people-roof'], 'not_sure' => ['Not sure', 'fa-question']]), true) ?>
        <div class="grid sm:grid-cols-2 gap-3">
          <?= $field('Ownership document', $select('land_title_status', OPT['title_status'], 'Select'), true) ?>
          <?= $field('Lease years remaining', $num('land_lease_years_remaining', 'leasehold only')) ?>
          <?= $field('Zoning / approved use', $select('land_zoning', OPT['zoning'], 'Select')) ?>
          <?= $field('Topography', $select('land_topography', OPT['topography'], 'Select')) ?>
          <?= $field('Soil type', $select('land_soil_type', OPT['soil_type'], 'Select')) ?>
          <?= $field('Road access', $select('land_road_access', OPT['road_access'], 'Select')) ?>
          <?= $field('Distance to tarmac (km)', '<input name="land_distance_to_tarmac_km" value="' . $val('land_distance_to_tarmac_km') . '" inputmode="decimal" class="vk-input" placeholder="e.g. 1.5">') ?>
        </div>
        <div class="grid sm:grid-cols-2 gap-x-6 gap-y-3">
          <?php foreach (['land_is_fenced' => 'Fenced', 'land_is_serviced' => 'Serviced (water / power on site)', 'land_is_subdivided' => 'Subdivided', 'land_is_corner_plot' => 'Corner plot', 'land_payment_plan' => 'Payment plan available'] as $k => $lab): ?>
            <label class="vk-switch"><input type="checkbox" name="<?= $k ?>" value="1" <?= $chk($k) ?>><?= e($lab) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- 5d. SERVICE -->
    <?php $sectionOpen('s-svc', 'fa-screwdriver-wrench', 'About your service', '', 'data-show-for="service"'); ?>
      <div class="space-y-5">
        <div class="grid sm:grid-cols-2 gap-3">
          <?= $field('You are', $pills('svc_provider_type', ['individual' => ['Individual', 'fa-user'], 'freelancer' => ['Freelancer', 'fa-user-gear'], 'company' => ['Company', 'fa-building'], 'agency' => ['Agency', 'fa-users']])) ?>
          <?= $field('Business / trading name', '<input name="svc_business_name" value="' . $val('svc_business_name') . '" class="vk-input" placeholder="optional">') ?>
        </div>
        <?= $field('How do you charge?', $pills('svc_pricing_model', array_map(fn($x) => $x, OPT['pricing_model'])), true) ?>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <?= $field('Years of experience', $num('svc_years_experience', 'e.g. 5')) ?>
          <?= $field('Team size', $select('svc_team_size', OPT['team_size'])) ?>
          <?= $field('Availability', $select('svc_availability', OPT['availability'])) ?>
          <?= $field('Response time', $select('svc_response_time', OPT['response_time'])) ?>
        </div>
        <div class="grid sm:grid-cols-2 gap-x-6 gap-y-3">
          <?php foreach (['svc_emergency_service' => ['24/7 emergency call-outs', 'fa-truck-medical'], 'svc_provides_materials' => ['I provide materials', 'fa-cubes'], 'svc_free_site_visit' => ['Free site visit / quote', 'fa-clipboard-check'], 'svc_is_insured' => ['Insured work', 'fa-shield-halved']] as $k => [$lab, $ic]): ?>
            <label class="vk-switch"><input type="checkbox" name="<?= $k ?>" value="1" <?= $chk($k) ?>><i class="fa-solid <?= $ic ?> text-muted"></i><?= e($lab) ?></label>
          <?php endforeach; ?>
        </div>
        <div class="grid sm:grid-cols-3 gap-3">
          <?= $field('Licensed / registered with', $select('svc_license_body', OPT['license_body'], 'Not licensed / N/A')) ?>
          <?= $field('Licence / reg. number', '<input name="svc_license_number" value="' . $val('svc_license_number') . '" class="vk-input" placeholder="optional">') ?>
          <?= $field('Warranty (months)', $num('svc_warranty_months', 'optional')) ?>
        </div>
        <?= $field('Languages', '<input name="svc_languages" value="' . $val('svc_languages') . '" class="vk-input" placeholder="e.g. English, Swahili">') ?>
        <div data-field-wrap>
          <label class="vk-label">Counties you serve</label>
          <input type="search" class="vk-input !py-2 mb-2" placeholder="Search counties…" id="area-search">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-1 max-h-56 overflow-y-auto rounded-2xl border border-line p-2" id="area-list">
            <?php foreach ($counties as $c): ?><label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-surface2 cursor-pointer" data-name="<?= e(strtolower($c['name'])) ?>"><input type="checkbox" name="service_areas[]" value="<?= $c['id'] ?>" <?= in_array((int)$c['id'], $areasSel, true) ? 'checked' : '' ?> class="h-4 w-4 accent-amber-600"><?= e($c['name']) ?></label><?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- 6. FEATURES -->
    <?php foreach (['residential' => 'residential', 'short_stay' => 'short_stay', 'commercial' => 'commercial', 'land' => 'land'] as $ff => $sf):
      $sectionOpen('s-feat-' . $ff, 'fa-star', $ff === 'land' ? 'Highlights' : 'Features & amenities', 'Tick everything that applies — buyers filter by these.', 'data-show-for="' . $sf . '"'); ?>
      <input type="search" class="vk-input !py-2 mb-3" placeholder="Search features…" data-feat-search>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2" data-feat-list>
        <?php foreach (features_for($ff) as $ft): ?>
          <label class="vk-choice" data-name="<?= e(strtolower($ft['name'])) ?>"><input type="checkbox" name="features[]" value="<?= $ft['id'] ?>" <?= in_array((int)$ft['id'], $featSel, true) ? 'checked' : '' ?>><span class="w-full"><i class="fa-solid <?= e($ft['icon']) ?>"></i><?= e($ft['name']) ?></span></label>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endforeach; ?>

    <!-- 7. PRICE -->
    <?php $sectionOpen('s-price', 'fa-tags', 'Price & terms'); ?>
      <div class="space-y-5">
        <div class="grid sm:grid-cols-2 gap-3">
          <div data-field-wrap>
            <label class="vk-label" for="price"><span data-price-label>Price (KSh)</span> <span class="req" data-price-req>*</span></label>
            <div class="vk-input-group"><i class="fa-solid fa-money-bill-wave"></i><input id="price" name="price" value="<?= $val('price') ?>" inputmode="numeric" class="vk-input font-bold text-lg" placeholder="0"></div>
            <p class="mt-1 text-xs text-muted" id="price-words"></p>
          </div>
          <div data-show-for="commercial"><?= $field('Price is', $pills('com_price_unit', OPT['price_unit_commercial'])) ?></div>
          <div data-show-for="land"><?= $field('Price is', $pills('land_price_unit', OPT['price_unit_land'])) ?></div>
        </div>
        <div data-deal-show="rent short_stay" data-field-wrap data-field="billing_period">
          <label class="vk-label">Billing period <span class="req">*</span></label>
          <div class="flex flex-wrap gap-2" id="billing-options">
            <?php foreach (OPT['billing_period'] as $k => $lab): ?><label class="vk-choice" data-billing="<?= $k ?>"><input type="radio" name="billing_period" value="<?= $k ?>" <?= $chk('billing_period', $k) ?>><span><?= e(ucfirst($lab)) ?></span></label><?php endforeach; ?>
          </div>
        </div>
        <label class="vk-switch"><input type="checkbox" name="is_negotiable" value="1" <?= $chk('is_negotiable') ?>>Price is negotiable</label>
        <div data-show-for="residential commercial short_stay" class="rounded-2xl border border-line p-4 space-y-3">
          <label class="vk-switch"><input type="checkbox" name="has_service_charge" value="1" id="has_sc" <?= $chk('has_service_charge') ?>>There is a service charge</label>
          <div id="sc-fields" class="grid sm:grid-cols-[1fr_auto] gap-3 <?= !empty($v['has_service_charge']) ? '' : 'hidden' ?>">
            <?= $field('Service charge amount (KSh)', $num('service_charge_amount', 'e.g. 5,000'), true) ?>
            <?= $field('Per', $select('service_charge_period', OPT['service_charge_period'], 'month')) ?>
            <?= $field('What does it cover?', '<input name="service_charge_notes" value="' . $val('service_charge_notes') . '" class="vk-input" placeholder="Security, garbage collection, cleaning of common areas…">', false, '', 'sm:col-span-2') ?>
          </div>
        </div>
        <div data-show-for="residential commercial land short_stay"><?= $field('Listing by', $pills('listed_by', ['owner' => ['Owner', 'fa-user'], 'agent' => ['Agent', 'fa-user-tie'], 'developer' => ['Developer', 'fa-helmet-safety'], 'company' => ['Company', 'fa-building']]), true) ?></div>
      </div>
    </section>

    <?php if ($officialMode): ?>
    <!-- 8. OFFICIAL STORE -->
    <?php $sectionOpen('s-official', 'fa-crown', 'Official store settings', 'Reservation fee, PMS link and the packages buyers choose from on the order page.'); ?>
      <div class="space-y-5">
        <div class="grid sm:grid-cols-3 gap-3">
          <?= $field('Reservation fee (KSh)', $num('reservation_fee', 'e.g. 50,000'), true, 'Paid via M-Pesa at checkout.') ?>
          <?= $field('PMS property ID', '<input name="pms_property_id" value="' . $val('pms_property_id') . '" class="vk-input" placeholder="PMS-PROP-0001">') ?>
          <?= $field('PMS unit ID', '<input name="pms_unit_id" value="' . $val('pms_unit_id') . '" class="vk-input" placeholder="A-12">') ?>
        </div>
        <div data-field="packages" data-field-wrap>
          <div class="flex items-center justify-between mb-2"><label class="vk-label !mb-0">Packages</label><button type="button" class="vk-btn-ghost !py-2 text-sm" id="add-package"><i class="fa-solid fa-plus"></i>Add package</button></div>
          <div id="packages" class="space-y-3"></div>
        </div>
      </div>
    </section>
    <template id="package-tpl">
      <div class="rounded-2xl border border-line p-4 grid sm:grid-cols-6 gap-3 relative" data-package>
        <button type="button" class="absolute top-2 right-2 vk-icon-btn !h-8 !w-8 text-rose-500" data-remove-package aria-label="Remove"><i class="fa-solid fa-xmark"></i></button>
        <div class="sm:col-span-2"><label class="vk-label">Name</label><input data-k="name" class="vk-input" placeholder="Cash purchase"></div>
        <div class="sm:col-span-2"><label class="vk-label">Type</label><select data-k="package_type" class="vk-select"><?php foreach (OPT['package_type'] as $k => $o): ?><option value="<?= $k ?>"><?= e($o) ?></option><?php endforeach; ?></select></div>
        <div class="sm:col-span-2"><label class="vk-label">Total price (KSh)</label><input data-k="total_price" inputmode="numeric" class="vk-input"></div>
        <div class="sm:col-span-2"><label class="vk-label">Deposit</label><input data-k="deposit_amount" inputmode="numeric" class="vk-input" placeholder="optional"></div>
        <div><label class="vk-label">Instalments</label><input data-k="installments" inputmode="numeric" class="vk-input" placeholder="—"></div>
        <div><label class="vk-label">Per instalment</label><input data-k="installment_amount" inputmode="numeric" class="vk-input" placeholder="—"></div>
        <div><label class="vk-label">Months</label><input data-k="duration_months" inputmode="numeric" class="vk-input" placeholder="—"></div>
        <div class="sm:col-span-5"><label class="vk-label">Description</label><input data-k="description" class="vk-input" placeholder="What's included / terms"></div>
        <div class="sm:col-span-6"><label class="vk-switch"><input type="checkbox" data-k="is_recommended" value="1">Mark as recommended</label></div>
      </div>
    </template>
    <?php endif; ?>
    </div>
  </div>

  <!-- sticky side: progress + submit -->
  <aside class="lg:sticky lg:top-32 space-y-4">
    <div class="vk-panel p-5">
      <p class="font-bold mb-3">Your ad checklist</p>
      <ul class="space-y-2 text-sm" id="checklist">
        <?php foreach (['category' => 'Category', 'title' => 'Title (8+ chars)', 'location' => 'Location', 'photos' => "$minPhotos+ photos", 'description' => 'Description (30+ chars)', 'price' => 'Price'] as $k => $lab): ?>
          <li class="flex items-center gap-2 text-muted" data-check="<?= $k ?>"><i class="fa-regular fa-circle"></i><?= e($lab) ?></li>
        <?php endforeach; ?>
      </ul>
      <div class="mt-4 h-2 rounded-full bg-surface2 overflow-hidden"><div id="progress" class="h-full w-0 bg-accent transition-all"></div></div>
      <button type="submit" class="vk-btn-accent w-full mt-5 !py-3.5 text-base" id="publish-btn"><i class="fa-solid <?= $officialMode ? 'fa-crown' : 'fa-paper-plane' ?>"></i><?= $officialMode ? ($edit ? 'Update official listing' : 'Publish to Official Store') : ($edit ? 'Submit changes' : 'Publish ad') ?></button>
      <p class="mt-3 text-xs text-muted text-center"><?= $officialMode ? '<i class="fa-solid fa-bolt"></i> Goes live immediately.' : '<i class="fa-solid fa-hourglass-half"></i> Reviewed by Vikao, usually within a few hours.' ?></p>
    </div>
    <div class="vk-panel p-5 text-sm text-muted space-y-2">
      <p class="font-bold text-ink"><i class="fa-solid fa-lightbulb text-amber-500"></i> Tips for a great ad</p>
      <p>Bright, horizontal photos sell faster. Start with the best exterior shot.</p>
      <p>A 360° video tour gets up to 3× more chats.</p>
      <p>Honest pricing builds trust — mention service charge upfront.</p>
    </div>
  </aside>
</form>
<?php ob_start(); ?>
<script>
window.VK_SELL = {
  categories: <?= json_encode(array_map(fn($c) => ['id' => (int)$c['id'], 'family' => $c['family'], 'deal' => $c['deal_type'], 'name' => $c['name']], $cats)) ?>,
  current: <?= json_encode($curCat ? ['id' => (int)$curCat['id'], 'family' => $curCat['family'], 'deal' => $curCat['deal_type']] : null) ?>,
  minPhotos: <?= $minPhotos ?>, maxBytes: <?= (int)config('uploads.max_bytes') ?>, maxPhotos: <?= (int)config('uploads.max_photos') ?>,
  descMax: <?= (int)config('listing.description_max') ?>, editing: <?= $edit ? 'true' : 'false' ?>,
  endpoint: <?= json_encode($officialMode ? '../pms/api.php' : 'listings.php') ?>, official: <?= $officialMode ? 'true' : 'false' ?>,
  packages: <?= json_encode(array_map(fn($p) => array_intersect_key($p, array_flip(['name', 'package_type', 'total_price', 'deposit_amount', 'installments', 'installment_amount', 'duration_months', 'description', 'is_recommended'])), $packages)) ?>
};
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
<script src="<?= asset('js/sell.js') ?>"></script>
<?php $extraScripts = ob_get_clean(); include APP_ROOT . '/includes/layout/footer.php'; ?>
