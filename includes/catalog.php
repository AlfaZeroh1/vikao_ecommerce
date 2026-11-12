<?php
/**
 * Catalog: option lists (enum labels + icons) and cached taxonomy lookups.
 * One source of truth for the Sell form, Shop filters and Ad page.
 */

const OPT = [
    'condition'   => ['new' => 'Newly built', 'used' => 'Used / Resale', 'renovated' => 'Renovated', 'off_plan' => 'Off-plan'],
    'furnishing'  => ['furnished' => 'Fully furnished', 'semi_furnished' => 'Semi furnished', 'unfurnished' => 'Unfurnished'],
    'listed_by'   => ['owner' => 'Owner', 'agent' => 'Agent', 'developer' => 'Developer', 'company' => 'Company'],
    'size_unit'   => ['sqm' => 'm²', 'sqft' => 'ft²'],
    'billing_period' => ['hour' => 'per hour', 'night' => 'per night', 'day' => 'per day', 'week' => 'per week', 'month' => 'per month', 'quarter' => 'per quarter', 'half_year' => 'per 6 months', 'year' => 'per year'],
    'price_unit_commercial' => ['entire' => 'Entire space', 'sqft' => 'Per ft²', 'sqm' => 'Per m²'],
    'price_unit_land' => ['entire' => 'Entire land', 'acre' => 'Per acre', 'hectare' => 'Per hectare', 'plot' => 'Per plot'],
    'service_charge_period' => ['month' => 'per month', 'quarter' => 'per quarter', 'year' => 'per year'],
    'land_size_unit' => ['acre' => 'Acres', 'hectare' => 'Hectares', 'plot_50x100' => 'Plots (50×100 ft)', 'plot_40x80' => 'Plots (40×80 ft)', 'plot_100x100' => 'Plots (100×100 ft)', 'sqm' => 'm²', 'sqft' => 'ft²'],
    'tenure'      => ['freehold' => 'Freehold', 'leasehold' => 'Leasehold', 'community' => 'Community land', 'not_sure' => 'Not sure'],
    'title_status'=> ['title_deed' => 'Title deed ready', 'allotment_letter' => 'Allotment letter', 'share_certificate' => 'Share certificate', 'lease_certificate' => 'Certificate of lease', 'pending_subdivision' => 'Pending subdivision', 'processing' => 'Title processing', 'none' => 'No document'],
    'zoning'      => ['residential' => 'Residential', 'commercial' => 'Commercial', 'agricultural' => 'Agricultural', 'industrial' => 'Industrial', 'mixed_use' => 'Mixed use', 'institutional' => 'Institutional'],
    'commercial_zoning' => ['commercial' => 'Commercial', 'industrial' => 'Industrial', 'mixed_use' => 'Mixed use', 'special' => 'Special use'],
    'topography'  => ['flat' => 'Flat', 'gentle_slope' => 'Gentle slope', 'steep' => 'Steep', 'valley' => 'Valley', 'hilltop' => 'Hilltop', 'waterfront' => 'Waterfront'],
    'soil_type'   => ['red_soil' => 'Red soil', 'black_cotton' => 'Black cotton', 'loam' => 'Loam', 'sandy' => 'Sandy', 'rocky' => 'Rocky', 'murram' => 'Murram', 'volcanic' => 'Volcanic'],
    'road_access' => ['tarmac' => 'Tarmac', 'murram' => 'Murram', 'gravel' => 'Gravel', 'footpath' => 'Footpath', 'none' => 'No road'],
    'power_phase' => ['single' => 'Single phase', 'three' => 'Three phase'],
    'provider_type' => ['individual' => 'Individual', 'freelancer' => 'Freelancer', 'company' => 'Company', 'agency' => 'Agency'],
    'pricing_model' => ['fixed' => 'Fixed price', 'hourly' => 'Per hour', 'daily' => 'Per day', 'per_sqm' => 'Per m²', 'per_unit' => 'Per unit / item', 'per_visit' => 'Per visit', 'quote' => 'On quotation'],
    'team_size'   => ['solo' => 'Just me', '2_5' => '2 – 5 people', '6_20' => '6 – 20 people', '21_50' => '21 – 50 people', '50_plus' => '50+ people'],
    'availability'=> ['weekdays' => 'Weekdays', 'weekends' => 'Weekends', 'everyday' => 'Every day', 'on_call' => 'On call / 24-7'],
    'response_time' => ['within_1h' => 'Within 1 hour', 'same_day' => 'Same day', 'within_24h' => 'Within 24 hours', 'within_3d' => 'Within 3 days'],
    'license_body' => ['NCA' => 'NCA (Construction)', 'EPRA' => 'EPRA (Energy / Electrical)', 'BORAQS' => 'BORAQS (Architects & QS)', 'EBK' => 'EBK (Engineers)', 'ISK' => 'ISK (Surveyors)', 'LSK' => 'LSK (Advocates)', 'EARB' => 'EARB (Estate Agents)', 'PCPB' => 'PCPB (Pest Control)', 'NEMA' => 'NEMA', 'WRA' => 'WRA (Water / Boreholes)', 'Other' => 'Other'],
    'package_type' => ['outright' => 'Outright purchase', 'installment' => 'Instalments', 'lease' => 'Lease', 'short_stay' => 'Short stay', 'custom' => 'Custom'],
    'posted_within' => ['1' => 'Last 24 hours', '7' => 'Last 7 days', '30' => 'Last 30 days'],
    'sort' => ['recommended' => 'Recommended', 'newest' => 'Newest first', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'popular' => 'Most viewed', 'size_desc' => 'Largest first'],
];

const STATUS_META = [
    'draft'    => ['Draft', 'bg-slate-500/15 text-slate-600 dark:text-slate-300', 'fa-file-pen'],
    'pending'  => ['Under review', 'bg-amber-500/15 text-amber-700 dark:text-amber-300', 'fa-hourglass-half'],
    'active'   => ['Active', 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300', 'fa-circle-check'],
    'rejected' => ['Declined', 'bg-rose-500/15 text-rose-700 dark:text-rose-300', 'fa-circle-xmark'],
    'closed'   => ['Closed', 'bg-slate-500/15 text-slate-600 dark:text-slate-300', 'fa-lock'],
    'expired'  => ['Expired', 'bg-slate-500/15 text-slate-600 dark:text-slate-300', 'fa-clock'],
    'sold'     => ['Sold / Let', 'bg-sky-500/15 text-sky-700 dark:text-sky-300', 'fa-handshake'],
    // orders
    'pending_payment' => ['Awaiting payment', 'bg-amber-500/15 text-amber-700 dark:text-amber-300', 'fa-hourglass-half'],
    'paid'       => ['Paid', 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300', 'fa-circle-check'],
    'processing' => ['Processing', 'bg-sky-500/15 text-sky-700 dark:text-sky-300', 'fa-gears'],
    'completed'  => ['Completed', 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300', 'fa-flag-checkered'],
    'cancelled'  => ['Cancelled', 'bg-rose-500/15 text-rose-700 dark:text-rose-300', 'fa-ban'],
    'refunded'   => ['Refunded', 'bg-slate-500/15 text-slate-600 dark:text-slate-300', 'fa-rotate-left'],
];

function opt(string $group): array { return OPT[$group] ?? []; }
function opt_label(string $group, ?string $key): string { return $key === null ? '' : (OPT[$group][$key] ?? ucwords(str_replace('_', ' ', $key))); }

function status_pill(string $status): string {
    [$label, $cls, $icon] = STATUS_META[$status] ?? [ucfirst($status), 'bg-slate-500/15 text-slate-600', 'fa-circle'];
    return '<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ' . $cls . '"><i class="fa-solid ' . $icon . '"></i>' . e($label) . '</span>';
}

/* ---------------- cached lookups ---------------- */
function categories(): array {
    static $c = null;
    if ($c === null) {
        $c = [];
        foreach (all("SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order") as $r) $c[(int)$r['id']] = $r;
    }
    return $c;
}
function category_by_slug(string $slug): ?array {
    foreach (categories() as $c) if ($c['slug'] === $slug) return $c;
    return null;
}
function category_children(?int $parentId): array {
    return array_values(array_filter(categories(), fn($c) => (int)$c['parent_id'] === (int)$parentId && ($parentId !== null || $c['parent_id'] === null)));
}
function root_categories(): array { return array_values(array_filter(categories(), fn($c) => $c['parent_id'] === null)); }
function postable_categories(): array { return array_values(array_filter(categories(), fn($c) => (int)$c['is_postable'] === 1)); }
function service_categories(): array { return array_values(array_filter(categories(), fn($c) => $c['family'] === 'service' && $c['parent_id'] !== null)); }

function property_types(string $family): array {
    static $cache = [];
    $fam = $family === 'short_stay' ? 'residential' : $family;
    return $cache[$fam] ??= all("SELECT * FROM property_types WHERE family = ? ORDER BY sort_order", [$fam]);
}
function features_for(string $family): array {
    static $cache = [];
    return $cache[$family] ??= all("SELECT * FROM features WHERE FIND_IN_SET(?, families) ORDER BY name", [$family]);
}

const FAMILY_META = [
    'residential' => ['Residential', 'fa-house-chimney', 'from-amber-500 to-orange-600'],
    'commercial'  => ['Commercial', 'fa-building', 'from-sky-500 to-indigo-600'],
    'land'        => ['Land', 'fa-mountain-sun', 'from-emerald-500 to-teal-600'],
    'short_stay'  => ['Short Stay', 'fa-bed', 'from-fuchsia-500 to-rose-500'],
    'service'     => ['Services', 'fa-screwdriver-wrench', 'from-slate-600 to-slate-800'],
];
function family_label(string $f): string { return FAMILY_META[$f][0] ?? ucfirst($f); }
function family_icon(string $f): string { return FAMILY_META[$f][1] ?? 'fa-tag'; }

function price_suffix(array $l): string {
    $s = '';
    if (in_array($l['price_unit'] ?? 'entire', ['sqft', 'sqm', 'acre', 'hectare', 'plot'], true)) $s .= ' / ' . ['sqft' => 'ft²', 'sqm' => 'm²', 'acre' => 'acre', 'hectare' => 'ha', 'plot' => 'plot'][$l['price_unit']];
    if (($l['billing_period'] ?? 'once') !== 'once') $s .= ' ' . opt_label('billing_period', $l['billing_period']);
    return $s;
}
