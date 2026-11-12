<?php
/**
 * Listings domain: save (create/edit) with validation, load, search, moderation.
 */

class ValidationError extends RuntimeException {
    public array $errors;
    public function __construct(array $errors, string $message = 'Please fix the highlighted fields.') { parent::__construct($message); $this->errors = $errors; }
}

const SQFT_TO_SQM = 0.09290304;
const ACRE_FACTORS = ['acre' => 1, 'hectare' => 2.4710538, 'sqm' => 1 / 4046.8564, 'sqft' => 1 / 43560, 'plot_50x100' => 5000 / 43560, 'plot_40x80' => 3200 / 43560, 'plot_100x100' => 10000 / 43560];

/* ------------------------------------------------------------------ locations */
function location_chain(int $locationId): array {
    $chain = [];
    $id = $locationId;
    while ($id && count($chain) < 6) {
        $row = one("SELECT id, parent_id, level, name, country_id, full_name FROM locations WHERE id = ?", [$id]);
        if (!$row) break;
        array_unshift($chain, $row);
        $id = $row['parent_id'];
    }
    return $chain;
}
/** descendant ids (inclusive) for a location filter */
function location_descendants(int $id): array {
    $ids = [$id]; $frontier = [$id];
    for ($i = 0; $i < 3 && $frontier; $i++) {
        $in = implode(',', array_map('intval', $frontier));
        $frontier = array_map('intval', array_column(all("SELECT id FROM locations WHERE parent_id IN ($in)"), 'id'));
        $ids = array_merge($ids, $frontier);
    }
    return $ids;
}

/* ------------------------------------------------------------------ save */
/**
 * Create or update a listing from request-style input.
 * $opts: official(bool) – posted by official store (skips review, allows reservation fee/packages)
 *        existing(array|null) – listing row for edits
 *        files(array) – uploaded photo list (see uploaded_files())
 *        remove_media(int[]) – media ids to delete (edit)
 *        local_files(string[]) – absolute paths to copy (seeding)
 */
function save_listing(array $in, array $user, array $opts = []): array {
    $existing = $opts['existing'] ?? null;
    $official = (bool)($opts['official'] ?? false);
    $files = $opts['files'] ?? [];
    $localFiles = $opts['local_files'] ?? [];
    $removeMedia = array_map('intval', $opts['remove_media'] ?? []);
    $err = [];
    $g = fn($k, $d = null) => isset($in[$k]) && $in[$k] !== '' ? (is_string($in[$k]) ? trim($in[$k]) : $in[$k]) : $d;
    $num = function ($k, $d = null) use ($in) { $v = $in[$k] ?? null; if (is_string($v)) $v = str_replace([',', ' '], '', $v); return ($v === null || $v === '' || !is_numeric($v)) ? $d : $v + 0; };
    $bool = fn($k) => !empty($in[$k]) && $in[$k] !== '0' ? 1 : 0;
    $triBool = fn($k) => (!isset($in[$k]) || $in[$k] === '') ? null : ((string)$in[$k] === '1' ? 1 : 0);
    $enum = fn($k, string $group) => ($v = $g($k)) !== null && array_key_exists($v, OPT[$group]) ? $v : null;

    // ---- core
    $title = $g('title', '');
    if (mb_strlen($title) < 8) $err['title'] = 'Title must be at least 8 characters.';
    if (mb_strlen($title) > config('listing.title_max')) $err['title'] = 'Title is too long (max ' . config('listing.title_max') . ').';

    $cat = categories()[(int)$g('category_id', 0)] ?? null;
    if (!$cat || !$cat['is_postable']) $err['category_id'] = 'Choose a category.';
    $family = $cat['family'] ?? 'residential';
    $deal = $cat['deal_type'] ?? 'sale';

    $locId = (int)($g('area_id') ?: $g('subcounty_id') ?: $g('county_id') ?: 0);
    $chain = $locId ? location_chain($locId) : [];
    if (!$chain) $err['county_id'] = 'Choose a location.';
    elseif ($family !== 'service' && count($chain) < 2) $err['subcounty_id'] = 'Choose the sub-county.';

    $descHtml = sanitize_rich_text((string)($in['description'] ?? ''));
    $descText = rich_to_text($descHtml);
    if (mb_strlen($descText) < 30) $err['description'] = 'Describe your listing in at least 30 characters.';
    if (mb_strlen($descText) > config('listing.description_max')) $err['description'] = 'Description is too long (max ' . config('listing.description_max') . ' characters).';

    $yt = $g('youtube_url'); $ytId = youtube_id($yt);
    if ($yt && !$ytId) $err['youtube_url'] = 'Paste a valid YouTube link (youtube.com/watch?v=… or youtu.be/…).';

    // ---- pricing
    $price = $num('price');
    if ($family !== 'service' && ($price === null || $price <= 0)) $err['price'] = 'Enter a price.';
    if ($price !== null && $price > 999999999999) $err['price'] = 'Price is too large.';
    $billing = 'once';
    if ($deal === 'rent' || $deal === 'short_stay') {
        $billing = $enum('billing_period', 'billing_period') ?? '';
        if (!$billing) $err['billing_period'] = 'Choose the billing period.';
    }
    $priceUnit = 'entire';
    if ($family === 'commercial') $priceUnit = $enum('com_price_unit', 'price_unit_commercial') ?? 'entire';
    if ($family === 'land') $priceUnit = $enum('land_price_unit', 'price_unit_land') ?? 'entire';
    $reservationFee = null;
    if ($official) {
        $reservationFee = $num('reservation_fee');
        if ($family !== 'service' && (!$reservationFee || $reservationFee <= 0)) $err['reservation_fee'] = 'Official listings need a reservation fee.';
    }
    $hasSc = $family === 'land' || $family === 'service' ? 0 : $bool('has_service_charge');
    $scAmount = $hasSc ? $num('service_charge_amount') : null;
    if ($hasSc && (!$scAmount || $scAmount <= 0)) $err['service_charge_amount'] = 'Enter the service charge amount.';

    $listedBy = $enum('listed_by', 'listed_by') ?? ($family === 'service' ? 'company' : null);
    if (!$listedBy) $err['listed_by'] = 'Who is listing this property?';

    // ---- family details
    $detail = []; $detailTable = null;
    if ($family === 'residential' || $family === 'short_stay') {
        $detailTable = 'listing_residential_details';
        $pt = (int)$g('res_property_type_id', 0);
        if (!in_array($pt, array_map('intval', array_column(property_types('residential'), 'id')), true)) $err['res_property_type_id'] = 'Choose the property type.';
        $sizeVal = $num('res_size_value'); $sizeUnit = $enum('res_size_unit', 'size_unit') ?? 'sqm';
        $detail = [
            'property_type_id' => $pt,
            'bedrooms' => $num('res_bedrooms'), 'bathrooms' => $num('res_bathrooms'), 'toilets' => $num('res_toilets'),
            'size_value' => $sizeVal, 'size_unit' => $sizeUnit,
            'size_sqm' => $sizeVal ? round($sizeUnit === 'sqft' ? $sizeVal * SQFT_TO_SQM : $sizeVal, 2) : null,
            'self_contained' => $triBool('res_self_contained'),
            'condition' => $enum('res_condition', 'condition'), 'furnishing' => $enum('res_furnishing', 'furnishing'),
            'floor_number' => $num('res_floor_number'), 'parking_spaces' => $num('res_parking_spaces'),
            'pets_allowed' => $triBool('res_pets_allowed'),
        ];
        if ($detail['bedrooms'] === null) $err['res_bedrooms'] = 'How many bedrooms? (0 for studio/bedsitter)';
        if ($detail['bathrooms'] === null) $err['res_bathrooms'] = 'How many bathrooms?';
        if ($family === 'short_stay') {
            $detail += [
                'max_guests' => $num('res_max_guests'), 'beds' => $num('res_beds'), 'min_nights' => $num('res_min_nights'),
                'check_in_time' => $g('res_check_in_time'), 'check_out_time' => $g('res_check_out_time'),
                'cleaning_fee' => $num('res_cleaning_fee'), 'security_deposit' => $num('res_security_deposit'),
            ];
            if (!$detail['max_guests']) $err['res_max_guests'] = 'How many guests can stay?';
        } else {
            if (!$detail['condition']) $err['res_condition'] = 'Choose the condition.';
            if (!$detail['furnishing']) $err['res_furnishing'] = 'Choose the furnishing.';
        }
    } elseif ($family === 'commercial') {
        $detailTable = 'listing_commercial_details';
        $pt = (int)$g('com_property_type_id', 0);
        if (!in_array($pt, array_map('intval', array_column(property_types('commercial'), 'id')), true)) $err['com_property_type_id'] = 'Choose the property type.';
        $sizeVal = $num('com_size_value'); $sizeUnit = $enum('com_size_unit', 'size_unit') ?? 'sqm';
        if (!$sizeVal) $err['com_size_value'] = 'Enter the size.';
        $detail = [
            'property_type_id' => $pt, 'size_value' => $sizeVal, 'size_unit' => $sizeUnit,
            'size_sqm' => $sizeVal ? round($sizeUnit === 'sqft' ? $sizeVal * SQFT_TO_SQM : $sizeVal, 2) : null,
            'parking_spaces' => $num('com_parking_spaces'), 'condition' => $enum('com_condition', 'condition'),
            'furnishing' => $enum('com_furnishing', 'furnishing'), 'floor_number' => $num('com_floor_number'),
            'total_floors' => $num('com_total_floors'), 'capacity_people' => $num('com_capacity_people'),
            'washrooms' => $num('com_washrooms'), 'power_phase' => $enum('com_power_phase', 'power_phase'),
            'zoning' => $enum('com_zoning', 'commercial_zoning'),
        ];
        if (!$detail['condition']) $err['com_condition'] = 'Choose the condition.';
    } elseif ($family === 'land') {
        $detailTable = 'listing_land_details';
        $pt = (int)$g('land_property_type_id', 0);
        if (!in_array($pt, array_map('intval', array_column(property_types('land'), 'id')), true)) $err['land_property_type_id'] = 'Choose the land type.';
        $sizeVal = $num('land_size_value'); $sizeUnit = $enum('land_size_unit', 'land_size_unit') ?? 'acre';
        if (!$sizeVal) $err['land_size_value'] = 'Enter the land size.';
        $detail = [
            'property_type_id' => $pt, 'size_value' => $sizeVal ?? 0, 'size_unit' => $sizeUnit,
            'size_acres' => $sizeVal ? round($sizeVal * ACRE_FACTORS[$sizeUnit], 4) : null,
            'tenure' => $enum('land_tenure', 'tenure'), 'lease_years_remaining' => $num('land_lease_years_remaining'),
            'title_status' => $enum('land_title_status', 'title_status'), 'zoning' => $enum('land_zoning', 'zoning'),
            'topography' => $enum('land_topography', 'topography'), 'soil_type' => $enum('land_soil_type', 'soil_type'),
            'road_access' => $enum('land_road_access', 'road_access'), 'distance_to_tarmac_km' => $num('land_distance_to_tarmac_km'),
            'is_corner_plot' => $triBool('land_is_corner_plot'), 'is_subdivided' => $triBool('land_is_subdivided'),
            'is_fenced' => $triBool('land_is_fenced'), 'is_serviced' => $triBool('land_is_serviced'),
            'payment_plan' => $bool('land_payment_plan'),
        ];
        if (!$detail['tenure']) $err['land_tenure'] = 'Choose the tenure.';
        if (!$detail['title_status']) $err['land_title_status'] = 'Choose the ownership document.';
    } else { // service
        $detailTable = 'listing_service_details';
        $pm = $enum('svc_pricing_model', 'pricing_model') ?? 'quote';
        $detail = [
            'provider_type' => $enum('svc_provider_type', 'provider_type') ?? 'individual',
            'business_name' => mb_substr((string)$g('svc_business_name', ''), 0, 140) ?: null,
            'pricing_model' => $pm, 'years_experience' => $num('svc_years_experience'),
            'team_size' => $enum('svc_team_size', 'team_size'), 'availability' => $enum('svc_availability', 'availability'),
            'emergency_service' => $bool('svc_emergency_service'), 'provides_materials' => $bool('svc_provides_materials'),
            'free_site_visit' => $bool('svc_free_site_visit'), 'is_insured' => $bool('svc_is_insured'),
            'warranty_months' => $num('svc_warranty_months'), 'license_body' => array_key_exists((string)$g('svc_license_body'), OPT['license_body']) ? $g('svc_license_body') : null,
            'license_number' => mb_substr((string)$g('svc_license_number', ''), 0, 80) ?: null,
            'response_time' => $enum('svc_response_time', 'response_time'),
            'languages' => mb_substr((string)$g('svc_languages', ''), 0, 120) ?: null,
        ];
        $priceUnit = ['hourly' => 'hour', 'daily' => 'day', 'per_sqm' => 'sqm_service', 'per_unit' => 'unit', 'per_visit' => 'visit', 'fixed' => 'job', 'quote' => 'job'][$pm];
        if ($pm !== 'quote' && (!$price || $price <= 0)) $err['price'] = 'Enter your starting price (or choose "On quotation").';
        if ($pm === 'quote') $price = $price ?: null;
    }

    // ---- photos
    $existingMediaCount = $existing ? (int)val("SELECT COUNT(*) FROM listing_media WHERE listing_id = ?", [$existing['id']]) : 0;
    $removeCount = $existing && $removeMedia ? (int)val("SELECT COUNT(*) FROM listing_media WHERE listing_id = ? AND id IN (" . implode(',', $removeMedia) . ")", [$existing['id']]) : 0;
    $photoErrors = validate_photos($files);
    if ($photoErrors) $err['photos'] = implode(' ', $photoErrors);
    $total = $existingMediaCount - $removeCount + count($files) + count($localFiles);
    $min = (int)config('uploads.min_photos', 6);
    if ($total < $min && empty($err['photos'])) $err['photos'] = "Add at least $min photos (you have $total).";
    if ($total > (int)config('uploads.max_photos', 20)) $err['photos'] = 'Maximum ' . config('uploads.max_photos') . ' photos.';

    // ---- packages (official)
    $packages = [];
    if ($official && !empty($in['packages']) && is_array($in['packages'])) {
        foreach ($in['packages'] as $i => $p) {
            if (empty($p['name']) && empty($p['total_price'])) continue;
            $tp = is_numeric(str_replace(',', '', (string)($p['total_price'] ?? ''))) ? (float)str_replace(',', '', $p['total_price']) : 0;
            if (!trim((string)($p['name'] ?? '')) || $tp <= 0) { $err["packages"] = 'Each package needs a name and total price.'; continue; }
            $packages[] = [
                'name' => mb_substr(trim($p['name']), 0, 100), 'description' => mb_substr(trim((string)($p['description'] ?? '')), 0, 500) ?: null,
                'package_type' => array_key_exists($p['package_type'] ?? '', OPT['package_type']) ? $p['package_type'] : 'outright',
                'total_price' => $tp,
                'deposit_amount' => is_numeric($p['deposit_amount'] ?? null) ? (float)$p['deposit_amount'] : null,
                'installments' => is_numeric($p['installments'] ?? null) ? (int)$p['installments'] : null,
                'installment_amount' => is_numeric($p['installment_amount'] ?? null) ? (float)$p['installment_amount'] : null,
                'duration_months' => is_numeric($p['duration_months'] ?? null) ? (int)$p['duration_months'] : null,
                'is_recommended' => !empty($p['is_recommended']) ? 1 : 0, 'sort_order' => count($packages),
            ];
        }
    }

    if ($err) throw new ValidationError($err);

    // ---- persist
    $region = $chain[0]; $leaf = end($chain);
    $row = [
        'category_id' => (int)$cat['id'], 'family' => $family, 'deal_type' => $deal,
        'title' => $title, 'slug' => slugify($title),
        'description_html' => $descHtml, 'description_text' => $descText,
        'country_id' => (int)$leaf['country_id'], 'location_id' => (int)$leaf['id'], 'region_id' => (int)$region['id'],
        'address' => mb_substr((string)$g('address', ''), 0, 255) ?: null,
        'estate' => mb_substr((string)$g('estate', ''), 0, 120) ?: null,
        'price' => $price, 'currency_code' => config('app.currency', 'KES'),
        'price_unit' => $priceUnit, 'billing_period' => $billing ?: 'once',
        'is_negotiable' => $bool('is_negotiable'), 'reservation_fee' => $reservationFee,
        'has_service_charge' => $hasSc, 'service_charge_amount' => $scAmount,
        'service_charge_period' => $hasSc ? ($enum('service_charge_period', 'service_charge_period') ?? 'month') : null,
        'service_charge_notes' => $hasSc ? (mb_substr((string)$g('service_charge_notes', ''), 0, 500) ?: null) : null,
        'listed_by' => $listedBy, 'youtube_url' => $ytId ? $yt : null, 'youtube_video_id' => $ytId,
        'is_official' => $official ? 1 : 0,
        'pms_property_id' => $official ? ($g('pms_property_id') ?: null) : null,
        'pms_unit_id' => $official ? ($g('pms_unit_id') ?: null) : null,
    ];
    $now = date('Y-m-d H:i:s');

    $listingId = tx(function () use ($existing, $row, $user, $official, $now, $detailTable, $detail, $family, $in, $files, $localFiles, $removeMedia, $packages) {
        $publishNow = $official; // PMS-posted official ads are published by the PMS itself
        if ($existing) {
            $id = (int)$existing['id'];
            $row['status'] = $publishNow ? 'active' : 'pending';
            $row['submitted_at'] = $now;
            if ($publishNow && !$existing['published_at']) $row['published_at'] = $now;
            update('listings', $row, 'id = :id', ['id' => $id]);
            foreach (['listing_residential_details', 'listing_commercial_details', 'listing_land_details', 'listing_service_details'] as $t) q("DELETE FROM $t WHERE listing_id = ?", [$id]);
            q("DELETE FROM listing_features WHERE listing_id = ?", [$id]);
            q("DELETE FROM listing_service_areas WHERE listing_id = ?", [$id]);
            if ($removeMedia) {
                $in_ = implode(',', $removeMedia);
                foreach (all("SELECT * FROM listing_media WHERE listing_id = ? AND id IN ($in_)", [$id]) as $m) delete_media_files($m);
                q("DELETE FROM listing_media WHERE listing_id = ? AND id IN ($in_)", [$id]);
            }
        } else {
            $row += [
                'uuid' => uuid4(), 'ref_code' => 'VK-' . random_code(6), 'user_id' => (int)$user['id'],
                'status' => $publishNow ? 'active' : 'pending', 'submitted_at' => $now,
                'published_at' => $publishNow ? $now : null,
                'is_premium' => $official ? 1 : 0,
            ];
            $id = insert('listings', $row);
        }
        insert($detailTable, ['listing_id' => $id] + $detail);

        // features — only those valid for this family
        $valid = array_map('intval', array_column(features_for($family), 'id'));
        $feat = array_values(array_intersect(array_map('intval', (array)($in['features'] ?? [])), $valid));
        foreach ($feat as $f) q("INSERT IGNORE INTO listing_features (listing_id, feature_id) VALUES (?, ?)", [$id, $f]);

        if ($family === 'service') {
            foreach (array_slice(array_map('intval', (array)($in['service_areas'] ?? [])), 0, 47) as $loc) q("INSERT IGNORE INTO listing_service_areas (listing_id, location_id) VALUES (?, ?)", [$id, $loc]);
        }

        // media
        $sort = (int)val("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM listing_media WHERE listing_id = ?", [$id]);
        foreach ($files as $f) store_listing_photo($id, $f, $sort++);
        foreach ($localFiles as $path) store_listing_photo($id, ['tmp_name' => $path, 'name' => basename($path)], $sort++, true);
        // optional client order (array of media ids)
        if (!empty($in['media_order']) && is_array($in['media_order'])) {
            foreach (array_values($in['media_order']) as $i => $mid) q("UPDATE listing_media SET sort_order = ? WHERE id = ? AND listing_id = ?", [$i, (int)$mid, $id]);
        }
        refresh_cover($id);

        if ($official) {
            q("DELETE FROM listing_packages WHERE listing_id = ?", [$id]);
            foreach ($packages as $p) insert('listing_packages', ['listing_id' => $id] + $p);
        }

        $type = $existing ? ($existing['status'] === 'closed' ? 'resubmit' : 'edit') : 'new';
        insert('listing_reviews', [
            'listing_id' => $id, 'submission_type' => $type,
            'snapshot' => json_encode(['title' => $row['title'], 'price' => $row['price'], 'category_id' => $row['category_id']]),
            'decision' => $publishNow ? 'approved' : 'pending', 'reviewer_source' => $publishNow ? 'pms' : 'pms',
            'reviewer_id' => $publishNow ? (int)$user['id'] : null, 'decided_at' => $publishNow ? $now : null,
        ]);
        if (!$publishNow) {
            pms_event($existing ? 'listing.updated' : 'listing.submitted', 'listing', $id, ['listing_id' => $id, 'title' => $row['title'], 'user_id' => (int)$user['id']]);
        }
        return $id;
    });

    log_activity((int)$user['id'], $existing ? 'listing.update' : 'listing.create', 'listing', $listingId);
    return one("SELECT * FROM listings WHERE id = ?", [$listingId]);
}

/* ------------------------------------------------------------------ read */
const CARD_SELECT = "l.id, l.uuid, l.ref_code, l.title, l.slug, l.family, l.deal_type, l.is_official, l.is_premium, l.price, l.currency_code,
    l.price_unit, l.billing_period, l.is_negotiable, l.reservation_fee, l.status, l.published_at, l.created_at, l.views_count, l.favourites_count,
    l.youtube_video_id, l.estate, l.user_id, l.category_id,
    loc.name AS location_name, loc.full_name AS location_full, reg.name AS region_name,
    m.thumb_path AS cover_thumb, m.path AS cover_path,
    (SELECT COUNT(*) FROM listing_media mm WHERE mm.listing_id = l.id) AS photo_count,
    u.display_name AS seller_name, u.account_type AS seller_type, u.avatar_url AS seller_avatar, mt.code AS seller_tier,
    EXISTS(SELECT 1 FROM user_badges ub WHERE ub.user_id = u.id AND ub.badge_id = 1) AS seller_verified,
    EXISTS(SELECT 1 FROM user_badges ub WHERE ub.user_id = u.id AND ub.badge_id = 2) AS seller_popular,
    rd.bedrooms, rd.bathrooms, rd.size_value AS res_size, rd.size_unit AS res_size_unit, rd.furnishing AS res_furnishing, rd.max_guests,
    cd.size_value AS com_size, cd.size_unit AS com_size_unit, cd.parking_spaces AS com_parking,
    ld.size_value AS land_size, ld.size_unit AS land_size_unit, ld.title_status,
    sd.pricing_model, sd.years_experience, sd.emergency_service,
    c.name AS category_name, c.icon AS category_icon,
    COALESCE(rpt.name, cpt.name, lpt.name) AS property_type_name";

const CARD_JOINS = "FROM listings l
    JOIN users u ON u.id = l.user_id
    LEFT JOIN membership_tiers mt ON mt.id = u.membership_tier_id
    JOIN categories c ON c.id = l.category_id
    JOIN locations loc ON loc.id = l.location_id
    LEFT JOIN locations reg ON reg.id = l.region_id
    LEFT JOIN listing_media m ON m.id = l.cover_media_id
    LEFT JOIN listing_residential_details rd ON rd.listing_id = l.id
    LEFT JOIN listing_commercial_details cd ON cd.listing_id = l.id
    LEFT JOIN listing_land_details ld ON ld.listing_id = l.id
    LEFT JOIN listing_service_details sd ON sd.listing_id = l.id
    LEFT JOIN property_types rpt ON rpt.id = rd.property_type_id
    LEFT JOIN property_types cpt ON cpt.id = cd.property_type_id
    LEFT JOIN property_types lpt ON lpt.id = ld.property_type_id";

function find_listing(string $uuid): ?array {
    return one("SELECT * FROM listings WHERE uuid = ? AND deleted_at IS NULL", [$uuid]);
}

/** Full listing for ad page / edit form. */
function load_listing_full(array $l): array {
    $id = (int)$l['id'];
    $l['category'] = categories()[(int)$l['category_id']] ?? null;
    $l['location_chain'] = location_chain((int)$l['location_id']);
    $l['media'] = all("SELECT * FROM listing_media WHERE listing_id = ? ORDER BY sort_order, id", [$id]);
    $l['features'] = all("SELECT f.* FROM listing_features lf JOIN features f ON f.id = lf.feature_id WHERE lf.listing_id = ? ORDER BY f.feature_group, f.name", [$id]);
    $l['details'] = match ($l['family']) {
        'residential', 'short_stay' => one("SELECT d.*, p.name AS property_type_name, p.icon AS property_type_icon FROM listing_residential_details d JOIN property_types p ON p.id = d.property_type_id WHERE d.listing_id = ?", [$id]),
        'commercial' => one("SELECT d.*, p.name AS property_type_name, p.icon AS property_type_icon FROM listing_commercial_details d JOIN property_types p ON p.id = d.property_type_id WHERE d.listing_id = ?", [$id]),
        'land' => one("SELECT d.*, p.name AS property_type_name, p.icon AS property_type_icon FROM listing_land_details d JOIN property_types p ON p.id = d.property_type_id WHERE d.listing_id = ?", [$id]),
        'service' => one("SELECT * FROM listing_service_details WHERE listing_id = ?", [$id]),
        default => null,
    } ?? [];
    $l['service_areas'] = $l['family'] === 'service' ? all("SELECT loc.* FROM listing_service_areas a JOIN locations loc ON loc.id = a.location_id WHERE a.listing_id = ? ORDER BY loc.name", [$id]) : [];
    $l['packages'] = all("SELECT * FROM listing_packages WHERE listing_id = ? AND is_active = 1 ORDER BY sort_order", [$id]);
    $l['seller'] = one("SELECT u.*, t.code AS tier_code, t.name AS tier_name, t.color_hex AS tier_color, t.icon AS tier_icon,
        (SELECT COUNT(*) FROM listings x WHERE x.user_id = u.id AND x.status = 'active' AND x.deleted_at IS NULL) AS active_ads
        FROM users u LEFT JOIN membership_tiers t ON t.id = u.membership_tier_id WHERE u.id = ?", [$l['user_id']]);
    $l['seller_badges'] = user_badges((int)$l['user_id']);
    return $l;
}

function bump_view(int $listingId): void {
    $key = 'viewed_' . $listingId;
    if (!empty($_SESSION[$key])) return;
    $_SESSION[$key] = 1;
    q("UPDATE listings SET views_count = views_count + 1 WHERE id = ?", [$listingId]);
    q("INSERT INTO listing_stats_daily (listing_id, stat_date, views) VALUES (?, CURDATE(), 1) ON DUPLICATE KEY UPDATE views = views + 1", [$listingId]);
}

function favourite_ids(?int $userId): array {
    if (!$userId) return [];
    static $c = null;
    return $c ??= array_flip(array_map('intval', array_column(all("SELECT listing_id FROM favourites WHERE user_id = ?", [$userId]), 'listing_id')));
}

/* ------------------------------------------------------------------ search */
/** Parse & whitelist shop filters from request. */
function shop_filters(): array {
    $f = [
        'q' => mb_substr((string)input('q', ''), 0, 100),
        'cat' => (string)input('cat', ''),             // category slug (root or leaf)
        'deal' => in_array(input('deal'), ['sale', 'rent', 'short_stay', 'service'], true) ? input('deal') : '',
        'county' => input_int('county'), 'subcounty' => input_int('subcounty'), 'area' => input_int('area'),
        'min_price' => input_float('min_price'), 'max_price' => input_float('max_price'),
        'billing' => array_values(array_intersect(input_array('billing'), array_keys(OPT['billing_period']))),
        'ptype' => array_map('intval', input_array('ptype')),
        'beds' => array_values(array_filter(input_array('beds'), fn($b) => preg_match('/^(0|[1-9]|5\+)$/', $b))),
        'baths' => input_int('baths'),
        'min_size' => input_float('min_size'), 'max_size' => input_float('max_size'),
        'furnishing' => array_values(array_intersect(input_array('furnishing'), array_keys(OPT['furnishing']))),
        'condition' => array_values(array_intersect(input_array('condition'), array_keys(OPT['condition']))),
        'listed_by' => array_values(array_intersect(input_array('listed_by'), array_keys(OPT['listed_by']))),
        'self_contained' => input('self_contained') === '1' ? 1 : null,
        'features' => array_map('intval', input_array('features')),
        'no_sc' => input('no_sc') === '1' ? 1 : null,
        'parking' => input_int('parking'),
        'guests' => input_int('guests'),
        'pets' => input('pets') === '1' ? 1 : null,
        'tenure' => array_values(array_intersect(input_array('tenure'), array_keys(OPT['tenure']))),
        'title_status' => array_values(array_intersect(input_array('title_status'), array_keys(OPT['title_status']))),
        'zoning' => array_values(array_intersect(input_array('zoning'), array_keys(OPT['zoning']))),
        'road' => array_values(array_intersect(input_array('road'), array_keys(OPT['road_access']))),
        'min_acres' => input_float('min_acres'), 'max_acres' => input_float('max_acres'),
        'payment_plan' => input('payment_plan') === '1' ? 1 : null,
        'pricing_model' => array_values(array_intersect(input_array('pricing_model'), array_keys(OPT['pricing_model']))),
        'emergency' => input('emergency') === '1' ? 1 : null,
        'materials' => input('materials') === '1' ? 1 : null,
        'licensed' => input('licensed') === '1' ? 1 : null,
        'official' => input('official') === '1' ? 1 : null,
        'verified' => input('verified') === '1' ? 1 : null,
        'tier' => array_values(array_intersect(input_array('tier'), ['bronze', 'silver', 'gold'])),
        'video' => input('video') === '1' ? 1 : null,
        'negotiable' => input('negotiable') === '1' ? 1 : null,
        'posted' => in_array(input('posted'), ['1', '7', '30'], true) ? (int)input('posted') : null,
        'sort' => array_key_exists((string)input('sort'), OPT['sort']) ? input('sort') : 'recommended',
        'page' => max(1, input_int('page', 1)),
    ];
    return $f;
}

/** Resolve active family for a filter set (drives which filter groups show). */
function filter_family(array $f): ?string {
    if ($f['cat'] && ($c = category_by_slug($f['cat']))) return $c['family'];
    if ($f['deal'] === 'short_stay') return 'short_stay';
    if ($f['deal'] === 'service') return 'service';
    return null;
}

function search_listings(array $f, ?int $perPage = null): array {
    $perPage = $perPage ?? (int)config('search.per_page', 24);
    $where = ["l.status = 'active'", 'l.deleted_at IS NULL'];
    $p = [];
    $family = filter_family($f);

    if ($f['cat'] && ($c = category_by_slug($f['cat']))) {
        if ($c['parent_id'] === null) { $where[] = 'l.family = :fam'; $p['fam'] = $c['family']; }
        else { $where[] = 'l.category_id = :cat'; $p['cat'] = (int)$c['id']; }
    }
    if ($f['deal']) { $where[] = 'l.deal_type = :deal'; $p['deal'] = $f['deal']; }

    if ($f['q'] !== '') {
        if (config('search.driver') === 'fulltext' && mb_strlen($f['q']) >= 3) {
            $terms = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $f['q']), -1, PREG_SPLIT_NO_EMPTY);
            $bool = implode(' ', array_map(fn($t) => '+' . $t . '*', array_slice($terms, 0, 6)));
            $where[] = '(MATCH(l.title, l.description_text, l.estate, l.address) AGAINST (:qb IN BOOLEAN MODE) OR loc.full_name LIKE :ql OR l.ref_code = :qr)';
            $p['qb'] = $bool; $p['ql'] = '%' . $f['q'] . '%'; $p['qr'] = strtoupper($f['q']);
        } else {
            $where[] = '(l.title LIKE :ql OR l.description_text LIKE :ql OR l.estate LIKE :ql OR loc.full_name LIKE :ql OR l.ref_code = :qr)';
            $p['ql'] = '%' . $f['q'] . '%'; $p['qr'] = strtoupper($f['q']);
        }
    }
    // location (deepest wins)
    $locFilter = $f['area'] ?: ($f['subcounty'] ?: $f['county']);
    if ($locFilter) {
        if ($f['county'] && !$f['subcounty'] && !$f['area']) {
            $where[] = '(l.region_id = :reg OR EXISTS(SELECT 1 FROM listing_service_areas sa WHERE sa.listing_id = l.id AND sa.location_id = :reg))';
            $p['reg'] = $f['county'];
        }
        else {
            $ids = implode(',', array_map('intval', location_descendants($locFilter)));
            $where[] = "(l.location_id IN ($ids)" . ($family === 'service' || $f['deal'] === 'service' ? " OR EXISTS(SELECT 1 FROM listing_service_areas sa WHERE sa.listing_id = l.id AND sa.location_id IN ($ids))" : '') . ')';
        }
    }
    if ($f['min_price'] !== null) { $where[] = 'l.price >= :minp'; $p['minp'] = $f['min_price']; }
    if ($f['max_price'] !== null) { $where[] = 'l.price <= :maxp'; $p['maxp'] = $f['max_price']; }
    if ($f['billing']) { $where[] = "l.billing_period IN ('" . implode("','", $f['billing']) . "')"; }
    if ($f['ptype']) { $where[] = 'COALESCE(rd.property_type_id, cd.property_type_id, ld.property_type_id) IN (' . implode(',', $f['ptype']) . ')'; }
    if ($f['beds']) {
        $or = [];
        foreach ($f['beds'] as $b) $or[] = $b === '5+' ? 'rd.bedrooms >= 5' : 'rd.bedrooms = ' . (int)$b;
        $where[] = '(' . implode(' OR ', $or) . ')';
    }
    if ($f['baths']) { $where[] = 'rd.bathrooms >= :baths'; $p['baths'] = $f['baths']; }
    if ($f['min_size'] !== null) { $where[] = 'COALESCE(rd.size_sqm, cd.size_sqm) >= :mins'; $p['mins'] = $f['min_size']; }
    if ($f['max_size'] !== null) { $where[] = 'COALESCE(rd.size_sqm, cd.size_sqm) <= :maxs'; $p['maxs'] = $f['max_size']; }
    if ($f['furnishing']) $where[] = "COALESCE(rd.furnishing, cd.furnishing) IN ('" . implode("','", $f['furnishing']) . "')";
    if ($f['condition']) $where[] = "COALESCE(rd.`condition`, cd.`condition`) IN ('" . implode("','", $f['condition']) . "')";
    if ($f['listed_by']) $where[] = "l.listed_by IN ('" . implode("','", $f['listed_by']) . "')";
    if ($f['self_contained']) $where[] = 'rd.self_contained = 1';
    if ($f['pets']) $where[] = 'rd.pets_allowed = 1';
    if ($f['guests']) { $where[] = 'rd.max_guests >= :guests'; $p['guests'] = $f['guests']; }
    if ($f['parking']) { $where[] = 'COALESCE(cd.parking_spaces, rd.parking_spaces) >= :park'; $p['park'] = $f['parking']; }
    if ($f['no_sc']) $where[] = 'l.has_service_charge = 0';
    foreach (array_slice($f['features'], 0, 15) as $i => $fid) { $where[] = "EXISTS(SELECT 1 FROM listing_features lf$i WHERE lf$i.listing_id = l.id AND lf$i.feature_id = " . (int)$fid . ')'; }
    if ($f['tenure']) $where[] = "ld.tenure IN ('" . implode("','", $f['tenure']) . "')";
    if ($f['title_status']) $where[] = "ld.title_status IN ('" . implode("','", $f['title_status']) . "')";
    if ($f['zoning']) $where[] = "ld.zoning IN ('" . implode("','", $f['zoning']) . "')";
    if ($f['road']) $where[] = "ld.road_access IN ('" . implode("','", $f['road']) . "')";
    if ($f['min_acres'] !== null) { $where[] = 'ld.size_acres >= :mina'; $p['mina'] = $f['min_acres']; }
    if ($f['max_acres'] !== null) { $where[] = 'ld.size_acres <= :maxa'; $p['maxa'] = $f['max_acres']; }
    if ($f['payment_plan']) $where[] = 'ld.payment_plan = 1';
    if ($f['pricing_model']) $where[] = "sd.pricing_model IN ('" . implode("','", $f['pricing_model']) . "')";
    if ($f['emergency']) $where[] = 'sd.emergency_service = 1';
    if ($f['materials']) $where[] = 'sd.provides_materials = 1';
    if ($f['licensed']) $where[] = 'sd.license_body IS NOT NULL';
    if ($f['official']) $where[] = 'l.is_official = 1';
    if ($f['verified']) $where[] = 'EXISTS(SELECT 1 FROM user_badges vb WHERE vb.user_id = l.user_id AND vb.badge_id = 1)';
    if ($f['tier']) $where[] = "mt.code IN ('" . implode("','", $f['tier']) . "')";
    if ($f['video']) $where[] = 'l.youtube_video_id IS NOT NULL';
    if ($f['negotiable']) $where[] = 'l.is_negotiable = 1';
    if ($f['posted']) { $where[] = 'l.published_at >= :posted'; $p['posted'] = date('Y-m-d H:i:s', strtotime("-{$f['posted']} days")); }

    $order = match ($f['sort']) {
        'newest' => 'l.published_at DESC',
        'price_asc' => 'l.price IS NULL, l.price ASC',
        'price_desc' => 'l.price DESC',
        'popular' => 'l.views_count DESC',
        'size_desc' => 'COALESCE(rd.size_sqm, cd.size_sqm, ld.size_acres * 4046.86) DESC',
        default => 'l.is_premium DESC, (l.views_count + l.favourites_count * 5 + l.chats_count * 8 + l.phone_reveals_count * 3 + 20) / (DATEDIFF(NOW(), l.published_at) + 3) DESC, l.published_at DESC',
    };
    $whereSql = implode(' AND ', $where);
    $total = (int)val("SELECT COUNT(*) " . CARD_JOINS . " WHERE $whereSql", $p);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($f['page'], $pages);
    $offset = ($page - 1) * $perPage;
    $rows = all("SELECT " . CARD_SELECT . " " . CARD_JOINS . " WHERE $whereSql ORDER BY $order LIMIT $perPage OFFSET $offset", $p);
    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'family' => $family];
}

function listing_cards(string $where, array $params = [], string $order = 'l.published_at DESC', int $limit = 12): array {
    return all("SELECT " . CARD_SELECT . " " . CARD_JOINS . " WHERE l.deleted_at IS NULL AND $where ORDER BY $order LIMIT $limit", $params);
}

/* ------------------------------------------------------------------ moderation */
function approve_listing(int $listingId, ?int $reviewerId): void {
    tx(function () use ($listingId, $reviewerId) {
        $l = one("SELECT * FROM listings WHERE id = ? FOR UPDATE", [$listingId]);
        if (!$l) throw new RuntimeException('Listing not found');
        $expiry = $l['is_official'] ? null : date('Y-m-d H:i:s', strtotime('+' . config('listing.expiry_days', 60) . ' days'));
        q("UPDATE listings SET status = 'active', published_at = COALESCE(published_at, NOW()), expires_at = ?, closed_at = NULL WHERE id = ?", [$expiry, $listingId]);
        q("UPDATE listing_reviews SET decision = 'approved', reviewer_id = ?, decided_at = NOW() WHERE listing_id = ? AND decision = 'pending'", [$reviewerId, $listingId]);
        notify((int)$l['user_id'], 'ad_approved', 'Your ad is live 🎉', '"' . $l['title'] . '" was approved and is now visible to buyers.', 'fa-circle-check', url('ad.php', ['id' => $l['uuid']]));
        $u = one("SELECT email, display_name FROM users WHERE id = ?", [$l['user_id']]);
        queue_email($u['email'], $u['display_name'], 'Your ad is live on Vikao', email_layout('Your ad is live 🎉', '<p>Great news — <b>' . e($l['title']) . '</b> has been approved and is now visible to buyers on Vikao.</p>'), 'ad_approved', 'listing', $listingId);
    });
}
function reject_listing(int $listingId, ?int $reviewerId, string $reason): void {
    tx(function () use ($listingId, $reviewerId, $reason) {
        $l = one("SELECT * FROM listings WHERE id = ?", [$listingId]);
        q("UPDATE listings SET status = 'rejected' WHERE id = ?", [$listingId]);
        q("UPDATE listing_reviews SET decision = 'rejected', reason = ?, reviewer_id = ?, decided_at = NOW() WHERE listing_id = ? AND decision = 'pending'", [$reason, $reviewerId, $listingId]);
        notify((int)$l['user_id'], 'ad_rejected', 'Your ad needs changes', '"' . $l['title'] . '" was declined: ' . $reason, 'fa-circle-exclamation', url('sell.php', ['edit' => $l['uuid']]));
    });
}
