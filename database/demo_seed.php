<?php
/**
 * Demo data for presentations: users, official store, listings across every family,
 * favourites, a chat, and a review queue for the mock PMS.
 * Included by install.php (bootstrap already loaded).
 */
$IMG = __DIR__ . '/demo-images';
$pass = 'Password123';

function demo_loc(string $name): int {
    $id = val("SELECT id FROM locations WHERE name = ? ORDER BY level DESC LIMIT 1", [$name]);
    if (!$id) throw new RuntimeException("Unknown demo location $name");
    return (int)$id;
}
function demo_county(string $name): int { return (int)val("SELECT id FROM locations WHERE name = ? AND level = 1", [$name]); }
function demo_cat(string $slug): int { return (int)category_by_slug($slug)['id']; }
function demo_ptype(string $family, string $name): int { return (int)val("SELECT id FROM property_types WHERE family = ? AND name = ?", [$family, $name]); }
function demo_feats(string $family, array $names): array {
    $in = implode(',', array_fill(0, count($names), '?'));
    return array_map('intval', array_column(all("SELECT id FROM features WHERE name IN ($in) AND FIND_IN_SET(?, families)", array_merge($names, [$family])), 'id'));
}
function demo_images(string $dir, array $kinds, int $n = 6): array {
    $all = [];
    foreach ($kinds as $k) foreach (glob("$dir/$k-*.jpg") as $f) $all[] = $f;
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        // copy to temp so the original stays (store_listing_photo copies from tmp_name)
        $src = $all[$i % count($all)];
        $tmp = tempnam(sys_get_temp_dir(), 'vk'); copy($src, $tmp); $out[] = $tmp;
    }
    return $out;
}
$P = fn(...$paras) => implode('', array_map(fn($p) => "<p>$p</p>", $paras));

echo "• demo users\n";
$official = create_user(['first_name' => 'Vikao', 'last_name' => 'Official Store', 'display_name' => 'Vikao Official Store', 'email' => 'official@vikao.io', 'phone' => '+254700100200', 'password' => 'Vikao@2026', 'membership_tier_id' => 3, 'email_verified_at' => date('Y-m-d H:i:s')], 'official', 'admin');
$staff = create_user(['first_name' => 'Pms', 'last_name' => 'Reviewer', 'display_name' => 'PMS Reviewer', 'email' => 'pms@vikao.io', 'phone' => '+254700100201', 'password' => 'Vikao@2026', 'membership_tier_id' => 3], 'normal', 'staff');
$users = [];
foreach ([
    ['Amina', 'Hassan', 'amina@demo.vikao.io', '+254712000111', 3],
    ['Brian', 'Kiprono', 'brian@demo.vikao.io', '+254712000222', 2],
    ['Wanjiru', 'Mwangi', 'wanjiru@demo.vikao.io', '+254712000333', 1],
    ['Otieno', 'Ouma', 'otieno@demo.vikao.io', '+254712000444', 2],
    ['Kevin', 'Mutua', 'kevin@demo.vikao.io', '+254712000555', 3],
] as [$f, $l, $e, $ph, $tier]) {
    $users[$f] = create_user(['first_name' => $f, 'last_name' => $l, 'email' => $e, 'phone' => $ph, 'password' => $pass, 'membership_tier_id' => $tier]);
}
// deterministic demo badges
q("DELETE FROM user_badges WHERE user_id IN (" . implode(',', $users) . ")");
foreach (['Amina' => [1, 2], 'Brian' => [1], 'Otieno' => [1, 2], 'Kevin' => [1]] as $n => $b) foreach ($b as $bid) q("INSERT INTO user_badges (user_id, badge_id) VALUES (?, ?)", [$users[$n], $bid]);

$U = fn($id) => one("SELECT * FROM users WHERE id = ?", [$id]);

$base = ['is_negotiable' => '0', 'listed_by' => 'owner'];
$listings = [
  // ---------- residential sale
  [$users['Amina'], ['house', 'villa'], $base + [
    'title' => '4 Bedroom Maisonette with DSQ in Runda Gardens', 'category_id' => demo_cat('residential-for-sale'), 'area_id' => demo_loc('Runda'),
    'estate' => 'Runda Gardens', 'address' => 'Off Kiambu Road', 'price' => '68500000', 'is_negotiable' => '1', 'listed_by' => 'agent',
    'description' => $P('Elegant <strong>4 bedroom maisonette</strong> on a quarter-acre in a quiet gated community in Runda.', 'All bedrooms en suite, a spacious lounge opening to a manicured garden, fitted kitchen with pantry, and a detached DSQ.', 'Ready title deed. Viewing by appointment.'),
    'res_property_type_id' => demo_ptype('residential', 'Maisonette'), 'res_bedrooms' => 4, 'res_bathrooms' => 5, 'res_size_value' => 380, 'res_size_unit' => 'sqm',
    'res_self_contained' => '1', 'res_condition' => 'used', 'res_furnishing' => 'unfurnished', 'res_parking_spaces' => 3,
    'has_service_charge' => '1', 'service_charge_amount' => '15000', 'service_charge_period' => 'month', 'service_charge_notes' => 'Security, garbage collection and common-area maintenance',
    'features' => demo_feats('residential', ['En Suite Bedrooms', 'DSQ / Staff Quarters', 'Garden', 'Gated Community', 'Backup Generator', 'Borehole', 'CCTV Surveillance', 'Electric Fence', 'Fitted Kitchen', 'Secure Parking']),
  ], 'active', 1200],
  [$users['Brian'], ['apartment'], $base + [
    'title' => 'Modern 2 Bedroom Apartment in Kilimani with Gym & Pool', 'category_id' => demo_cat('residential-for-sale'), 'area_id' => demo_loc('Kilimani'),
    'estate' => 'Lenana Road', 'price' => '14200000', 'listed_by' => 'developer',
    'description' => $P('Brand-new 2 bedroom apartment, master en suite, open-plan kitchen and a large balcony with city views.', 'Amenities: rooftop pool, gym, high-speed lifts, backup generator and 24-hour security.'),
    'res_property_type_id' => demo_ptype('residential', 'Apartment'), 'res_bedrooms' => 2, 'res_bathrooms' => 2, 'res_size_value' => 1180, 'res_size_unit' => 'sqft',
    'res_self_contained' => '1', 'res_condition' => 'new', 'res_furnishing' => 'unfurnished', 'res_floor_number' => 9, 'res_parking_spaces' => 1,
    'has_service_charge' => '1', 'service_charge_amount' => '9000', 'service_charge_period' => 'month',
    'features' => demo_feats('residential', ['Swimming Pool', 'Gym', 'Elevator / Lift', 'Balcony', 'Backup Generator', '24-hour Security', 'Fibre Internet Ready', 'Rooftop Terrace']),
  ], 'active', 940],
  // ---------- residential rent
  [$users['Wanjiru'], ['apartment', 'house'], $base + [
    'title' => 'Spacious Bedsitter to Let in Ruaka near Two Rivers', 'category_id' => demo_cat('residential-for-rent'), 'area_id' => demo_loc('Ruaka'),
    'price' => '13500', 'billing_period' => 'month',
    'description' => $P('Clean, well-lit bedsitter with separate kitchenette and tiled floors. Instant shower, water 24/7 and prepaid tokens.', '5 minutes to Ruaka town and Two Rivers Mall.'),
    'res_property_type_id' => demo_ptype('residential', 'Bedsitter'), 'res_bedrooms' => 0, 'res_bathrooms' => 1, 'res_size_value' => 28, 'res_size_unit' => 'sqm',
    'res_self_contained' => '1', 'res_condition' => 'used', 'res_furnishing' => 'unfurnished', 'res_floor_number' => 2,
    'features' => demo_feats('residential', ['Pre-paid Meter', 'Hot Water / Instant Shower', 'Water Storage Tank', 'Tiled Floors', 'CCTV Surveillance']),
  ], 'active', 410],
  [$users['Otieno'], ['house'], $base + [
    'title' => '3 Bedroom Bungalow for Rent in Milimani, Kisumu', 'category_id' => demo_cat('residential-for-rent'), 'area_id' => demo_loc('Milimani (Kisumu)'),
    'price' => '65000', 'billing_period' => 'month', 'listed_by' => 'agent', 'is_negotiable' => '1',
    'description' => $P('Charming 3 bedroom bungalow on a leafy compound with lake breeze. Master en suite, own compound and secure parking for 2 cars.'),
    'res_property_type_id' => demo_ptype('residential', 'Bungalow'), 'res_bedrooms' => 3, 'res_bathrooms' => 2, 'res_size_value' => 210, 'res_size_unit' => 'sqm',
    'res_self_contained' => '1', 'res_condition' => 'renovated', 'res_furnishing' => 'semi_furnished', 'res_parking_spaces' => 2, 'res_pets_allowed' => '1',
    'features' => demo_feats('residential', ['Own Compound', 'Garden', 'Secure Parking', 'Night Security Only', 'Built-in Wardrobes', 'Pet Friendly']),
  ], 'active', 260],
  // ---------- commercial
  [$users['Brian'], ['office'], $base + [
    'title' => 'Grade A Office Space to Let in Westlands — 2,400 sq ft', 'category_id' => demo_cat('commercial-for-rent'), 'area_id' => demo_loc('Westlands'),
    'estate' => 'Woodvale Grove', 'price' => '130', 'com_price_unit' => 'sqft', 'billing_period' => 'month', 'listed_by' => 'agent',
    'description' => $P('Fitted Grade A office floor with boardroom, reception and server room. Backup generator, high-speed lifts and ample parking (1:400 ft²).'),
    'com_property_type_id' => demo_ptype('commercial', 'Office'), 'com_size_value' => 2400, 'com_size_unit' => 'sqft', 'com_parking_spaces' => 6,
    'com_condition' => 'new', 'com_furnishing' => 'semi_furnished', 'com_floor_number' => 6, 'com_total_floors' => 12, 'com_power_phase' => 'three', 'com_zoning' => 'commercial',
    'has_service_charge' => '1', 'service_charge_amount' => '35', 'service_charge_period' => 'month', 'service_charge_notes' => 'KSh 35 per ft² per month',
    'features' => demo_feats('commercial', ['Boardroom', 'Reception Area', 'Server Room', 'Backup Generator', 'Elevator / Lift', 'Visitor Parking', 'Fire Sprinkler System', 'Access Control / Biometric Entry']),
  ], 'active', 380],
  [$users['Otieno'], ['warehouse'], $base + [
    'title' => '10,000 sq ft Godown for Sale on Mombasa Road', 'category_id' => demo_cat('commercial-for-sale'), 'area_id' => demo_loc('Athi River'),
    'price' => '95000000', 'listed_by' => 'owner',
    'description' => $P('Steel-frame godown on 1/2 acre with 3 roller-shutter doors, 9m eaves height, office block and three-phase power. Trucks access from Mombasa Road.'),
    'com_property_type_id' => demo_ptype('commercial', 'Godown'), 'com_size_value' => 10000, 'com_size_unit' => 'sqft', 'com_parking_spaces' => 12,
    'com_condition' => 'used', 'com_furnishing' => 'unfurnished', 'com_power_phase' => 'three', 'com_zoning' => 'industrial',
    'features' => demo_feats('commercial', ['Three-phase Power', 'Truck Access', 'Dedicated Loading Bay', 'Borehole', 'Night Security Only', 'High Ceilings']),
  ], 'active', 190],
  [$users['Amina'], ['shop'], $base + [
    'title' => 'Ground-floor Shop to Let on Moi Avenue, Nairobi CBD', 'category_id' => demo_cat('commercial-for-rent'), 'area_id' => demo_loc('Nairobi CBD'),
    'price' => '85000', 'billing_period' => 'month', 'com_price_unit' => 'entire',
    'description' => $P('Prime street-facing shop with glass frontage and heavy foot traffic. Ideal for retail, pharmacy or mobile-money agency.'),
    'com_property_type_id' => demo_ptype('commercial', 'Shop'), 'com_size_value' => 45, 'com_size_unit' => 'sqm', 'com_condition' => 'renovated', 'com_furnishing' => 'unfurnished',
    'features' => demo_feats('commercial', ['Frontage / Street Visibility', 'Signage Space', 'CCTV Surveillance', 'Bus Stage Nearby']),
  ], 'active', 300],
  // ---------- land
  [$users['Wanjiru'], ['land'], $base + [
    'title' => '1/8 Acre Plots in Kitengela — Ready Title Deeds', 'category_id' => demo_cat('land-for-sale'), 'area_id' => demo_loc('Kitengela'),
    'price' => '1250000', 'land_price_unit' => 'plot', 'listed_by' => 'company', 'is_negotiable' => '1',
    'description' => $P('Serviced 50×100 plots, 1.5km off Namanga Road. Water and electricity on site, murram roads and perimeter fence.', 'Pay in 12 months with 30% deposit. Free site visits every Saturday.'),
    'land_property_type_id' => demo_ptype('land', 'Residential Plot'), 'land_size_value' => 1, 'land_size_unit' => 'plot_50x100',
    'land_tenure' => 'freehold', 'land_title_status' => 'title_deed', 'land_zoning' => 'residential', 'land_topography' => 'flat', 'land_soil_type' => 'red_soil',
    'land_road_access' => 'murram', 'land_distance_to_tarmac_km' => '1.5', 'land_is_fenced' => '1', 'land_is_serviced' => '1', 'land_payment_plan' => '1',
    'features' => demo_feats('land', ['Water Connection', 'Electricity Connection', 'Beacons in Place', 'Ready Title Deed', 'Flexible Payment Plan', 'Ready to Build']),
  ], 'active', 720],
  [$users['Otieno'], ['land'], $base + [
    'title' => '5 Acres Agricultural Land for Lease in Naivasha', 'category_id' => demo_cat('land-for-rent'), 'area_id' => demo_loc('Naivasha Town'),
    'price' => '40000', 'land_price_unit' => 'acre', 'billing_period' => 'year',
    'description' => $P('Fertile, flat land with a borehole and irrigation lines. Suitable for horticulture. Minimum lease 3 years.'),
    'land_property_type_id' => demo_ptype('land', 'Agricultural / Farmland'), 'land_size_value' => 5, 'land_size_unit' => 'acre',
    'land_tenure' => 'freehold', 'land_title_status' => 'title_deed', 'land_zoning' => 'agricultural', 'land_topography' => 'flat', 'land_soil_type' => 'loam', 'land_road_access' => 'gravel',
    'features' => demo_feats('land', ['Borehole', 'Fertile Soil', 'Irrigation Ready', 'Perimeter Fence / Wall']),
  ], 'active', 120],
  // ---------- short stay
  [$users['Amina'], ['bnb', 'villa'], $base + [
    'title' => 'Beachfront 3BR Villa in Diani with Private Pool', 'category_id' => demo_cat('short-stay'), 'area_id' => demo_loc('Diani'),
    'price' => '28000', 'billing_period' => 'night',
    'description' => $P('Wake up to the Indian Ocean. Private pool, chef on request, Wi-Fi and daily housekeeping. 3 minutes walk to the beach.'),
    'res_property_type_id' => demo_ptype('residential', 'Villa'), 'res_bedrooms' => 3, 'res_bathrooms' => 3, 'res_max_guests' => 6, 'res_beds' => 4, 'res_min_nights' => 2,
    'res_check_in_time' => '14:00', 'res_check_out_time' => '10:00', 'res_cleaning_fee' => '3000', 'res_security_deposit' => '10000',
    'features' => demo_feats('short_stay', ['Swimming Pool', 'Beach Access', 'Wi-Fi', 'Air Conditioning', 'Daily Housekeeping', 'Free Parking on Premises', 'Smart TV & Streaming', 'Linen & Towels']),
  ], 'active', 860],
  [$users['Brian'], ['apartment', 'bnb'], $base + [
    'title' => 'Cosy 1BR Airbnb in Kilimani — Netflix & Fast Wi-Fi', 'category_id' => demo_cat('short-stay'), 'area_id' => demo_loc('Kilimani'),
    'price' => '6500', 'billing_period' => 'night',
    'description' => $P('Stylish one-bedroom with workspace, self check-in and rooftop views. Near Yaya Centre.'),
    'res_property_type_id' => demo_ptype('residential', 'Apartment'), 'res_bedrooms' => 1, 'res_bathrooms' => 1, 'res_max_guests' => 2, 'res_beds' => 1, 'res_min_nights' => 1,
    'res_check_in_time' => '13:00', 'res_check_out_time' => '11:00',
    'features' => demo_feats('short_stay', ['Wi-Fi', 'Netflix', 'Self Check-in', 'Workspace / Desk', 'Hot Water / Instant Shower', 'Rooftop Terrace']),
  ], 'active', 540],
  // ---------- services
  [$users['Kevin'], ['service'], $base + [
    'title' => 'Licensed Plumber — Leaks, Installations & Borehole Pumps', 'category_id' => demo_cat('services-plumbing'), 'county_id' => demo_county('Nairobi'),
    'price' => '1500', 'listed_by' => 'company',
    'description' => $P('Fast, tidy and guaranteed plumbing for homes and offices: leak repairs, new installations, water heaters, pumps and tanks.', 'Same-day response across Nairobi and Kiambu.'),
    'svc_provider_type' => 'company', 'svc_business_name' => 'Mutua Plumbing Works', 'svc_pricing_model' => 'per_visit', 'svc_years_experience' => 9, 'svc_team_size' => '2_5',
    'svc_availability' => 'everyday', 'svc_emergency_service' => '1', 'svc_provides_materials' => '1', 'svc_free_site_visit' => '1', 'svc_warranty_months' => 6,
    'svc_license_body' => 'NCA', 'svc_license_number' => 'NCA-8/PL/2291', 'svc_response_time' => 'same_day', 'svc_languages' => 'English, Swahili, Kamba',
    'service_areas' => [demo_county('Nairobi'), demo_county('Kiambu')],
  ], 'active', 330],
  [$users['Kevin'], ['service'], $base + [
    'title' => 'Professional Movers — Homes & Offices, Fully Insured', 'category_id' => demo_cat('services-movers-and-relocation'), 'county_id' => demo_county('Nairobi'),
    'price' => '', 'listed_by' => 'company',
    'description' => $P('Packing, wrapping, transport and unpacking with a trained crew. Fully insured moves within Nairobi and upcountry.'),
    'svc_provider_type' => 'company', 'svc_business_name' => 'SwiftMove Kenya', 'svc_pricing_model' => 'quote', 'svc_years_experience' => 6, 'svc_team_size' => '6_20',
    'svc_availability' => 'everyday', 'svc_is_insured' => '1', 'svc_provides_materials' => '1', 'svc_free_site_visit' => '1', 'svc_response_time' => 'within_24h',
    'service_areas' => [demo_county('Nairobi'), demo_county('Kiambu'), demo_county('Machakos'), demo_county('Kajiado')],
  ], 'active', 210],
  // ---------- review queue (pending)
  [$users['Wanjiru'], ['house'], $base + [
    'title' => '3 Bedroom Townhouse for Sale in Syokimau', 'category_id' => demo_cat('residential-for-sale'), 'area_id' => demo_loc('Syokimau'),
    'price' => '11800000', 'listed_by' => 'owner',
    'description' => $P('Lovely townhouse in a gated community near the SGR terminus. Master en suite, gym and children\'s play area.'),
    'res_property_type_id' => demo_ptype('residential', 'Townhouse'), 'res_bedrooms' => 3, 'res_bathrooms' => 3, 'res_size_value' => 160, 'res_size_unit' => 'sqm',
    'res_self_contained' => '1', 'res_condition' => 'new', 'res_furnishing' => 'unfurnished',
    'features' => demo_feats('residential', ['Gated Community', "Children's Play Area", 'Gym', 'Borehole']),
  ], 'pending', 0],
  [$users['Brian'], ['office', 'shop'], $base + [
    'title' => 'Co-working Desks Available in Upper Hill', 'category_id' => demo_cat('commercial-for-rent'), 'area_id' => demo_loc('Upper Hill'),
    'price' => '12000', 'billing_period' => 'month',
    'description' => $P('Hot desks and dedicated desks with fibre internet, coffee and meeting rooms. Flexible monthly plans.'),
    'com_property_type_id' => demo_ptype('commercial', 'Co-working Space'), 'com_size_value' => 6, 'com_size_unit' => 'sqm', 'com_condition' => 'new', 'com_furnishing' => 'furnished',
    'features' => demo_feats('commercial', ['Wi-Fi', 'Kitchenette', 'Conference Facilities', 'Open-plan Layout']),
  ], 'pending', 0],
];

echo "• demo listings\n";
$created = [];
foreach ($listings as $i => [$uid, $kinds, $in, $status, $views]) {
    $user = $U($uid);
    $l = save_listing($in, $user, ['local_files' => demo_images($IMG, $kinds, 6)]);
    if ($status === 'active') approve_listing((int)$l['id'], $staff);
    $days = random_int(0, 18);
    q("UPDATE listings SET views_count = ?, favourites_count = ?, published_at = IF(status='active', NOW() - INTERVAL ? DAY - INTERVAL ? HOUR, NULL), created_at = NOW() - INTERVAL ? DAY WHERE id = ?",
      [$views, intdiv($views, 40), $days, random_int(0, 20), $days, $l['id']]);
    $created[] = $l;
}
// premium boost on two
q("UPDATE listings SET is_premium = 1 WHERE id IN (?, ?)", [$created[0]['id'], $created[9]['id']]);

echo "• official store listings\n";
$officialUser = $U($official);
$officialListings = [
  [['villa', 'house'], [
    'title' => 'Karen Ridge Villas — 5 Bedroom Smart Home', 'category_id' => demo_cat('residential-for-sale'), 'area_id' => demo_loc('Karen'),
    'estate' => 'Karen Ridge', 'price' => '125000000', 'reservation_fee' => '250000', 'listed_by' => 'developer',
    'description' => $P('Managed by <strong>Vikao</strong>. A contemporary 5 bedroom villa on 1/2 acre with smart-home automation, solar backup, heated pool and landscaped gardens.', 'Reserve online to lock in this unit. Your reservation fee is deducted from the final price.'),
    'res_property_type_id' => demo_ptype('residential', 'Villa'), 'res_bedrooms' => 5, 'res_bathrooms' => 6, 'res_size_value' => 650, 'res_size_unit' => 'sqm',
    'res_self_contained' => '1', 'res_condition' => 'new', 'res_furnishing' => 'semi_furnished', 'res_parking_spaces' => 4,
    'features' => demo_feats('residential', ['IoT / Smart Home', 'Swimming Pool', 'Solar Water Heating', 'Backup Generator', 'En Suite Bedrooms', 'Gym', 'Garden', 'CCTV Surveillance', 'Electric Fence', 'Walk-in Closet', 'DSQ / Staff Quarters']),
    'pms_property_id' => 'PMS-PROP-0012', 'pms_unit_id' => 'KR-V05',
    'packages' => [
      ['name' => 'Cash purchase', 'package_type' => 'outright', 'total_price' => '119000000', 'description' => '5% discount for full payment within 90 days.', 'is_recommended' => '1'],
      ['name' => '24-month instalments', 'package_type' => 'installment', 'total_price' => '128500000', 'deposit_amount' => '25000000', 'installments' => '24', 'installment_amount' => '4312500', 'description' => '20% deposit, balance over 24 months interest-free.'],
      ['name' => 'Mortgage (partner bank)', 'package_type' => 'custom', 'total_price' => '125000000', 'deposit_amount' => '12500000', 'description' => '10% deposit, financing via our partner banks.'],
    ],
  ]],
  [['apartment'], [
    'title' => 'The Vikao Residences Kileleshwa — 3BR Units', 'category_id' => demo_cat('residential-for-rent'), 'area_id' => demo_loc('Kileleshwa'),
    'price' => '180000', 'billing_period' => 'month', 'reservation_fee' => '20000', 'listed_by' => 'company',
    'description' => $P('Fully serviced 3 bedroom apartments with DSQ, managed by Vikao. Gym, pool, kids\' area, backup power and 24/7 concierge.'),
    'res_property_type_id' => demo_ptype('residential', 'Apartment'), 'res_bedrooms' => 3, 'res_bathrooms' => 4, 'res_size_value' => 210, 'res_size_unit' => 'sqm',
    'res_self_contained' => '1', 'res_condition' => 'new', 'res_furnishing' => 'unfurnished', 'res_floor_number' => 7, 'res_parking_spaces' => 2,
    'has_service_charge' => '1', 'service_charge_amount' => '18000', 'service_charge_period' => 'month',
    'features' => demo_feats('residential', ['Swimming Pool', 'Gym', 'Elevator / Lift', 'DSQ / Staff Quarters', 'Backup Generator', '24-hour Security', "Children's Play Area", 'Intercom']),
    'pms_property_id' => 'PMS-PROP-0007', 'pms_unit_id' => 'VRK-7B',
    'packages' => [
      ['name' => '12-month lease', 'package_type' => 'lease', 'total_price' => '2160000', 'deposit_amount' => '360000', 'duration_months' => '12', 'description' => '2 months deposit + first month rent.', 'is_recommended' => '1'],
      ['name' => '24-month lease', 'package_type' => 'lease', 'total_price' => '4104000', 'deposit_amount' => '360000', 'duration_months' => '24', 'description' => '5% off monthly rent when you commit for 2 years.'],
    ],
  ]],
  [['land'], [
    'title' => 'Vikao Gardens Kitengela — Serviced 50×100 Plots', 'category_id' => demo_cat('land-for-sale'), 'area_id' => demo_loc('Kitengela'),
    'price' => '1650000', 'land_price_unit' => 'plot', 'reservation_fee' => '50000', 'listed_by' => 'company',
    'description' => $P('Gated, serviced plots with cabro roads, water and power. Individual title deeds. Reserve a plot today and pick your preferred position at site visit.'),
    'land_property_type_id' => demo_ptype('land', 'Gated Community Plot'), 'land_size_value' => 1, 'land_size_unit' => 'plot_50x100',
    'land_tenure' => 'freehold', 'land_title_status' => 'title_deed', 'land_zoning' => 'residential', 'land_topography' => 'flat', 'land_road_access' => 'tarmac',
    'land_is_fenced' => '1', 'land_is_serviced' => '1', 'land_payment_plan' => '1', 'land_is_subdivided' => '1',
    'features' => demo_feats('land', ['Water Connection', 'Electricity Connection', 'Ready Title Deed', 'Gated Community', 'Controlled Development', 'Flexible Payment Plan', 'Tarmac Road Frontage']),
    'pms_property_id' => 'PMS-PROP-0021', 'pms_unit_id' => 'VGK-PLOTS',
    'packages' => [
      ['name' => 'Cash price', 'package_type' => 'outright', 'total_price' => '1500000', 'description' => 'Pay in full within 30 days.', 'is_recommended' => '1'],
      ['name' => '12-month plan', 'package_type' => 'installment', 'total_price' => '1650000', 'deposit_amount' => '300000', 'installments' => '12', 'installment_amount' => '112500'],
    ],
  ]],
  [['office'], [
    'title' => 'Vikao Business Park Upper Hill — Office Suites', 'category_id' => demo_cat('commercial-for-rent'), 'area_id' => demo_loc('Upper Hill'),
    'price' => '140', 'com_price_unit' => 'sqft', 'billing_period' => 'month', 'reservation_fee' => '30000', 'listed_by' => 'company',
    'description' => $P('Plug-and-play office suites from 1,200 ft² in a green-certified tower. Raised floors, fibre, backup power and 1:300 parking.'),
    'com_property_type_id' => demo_ptype('commercial', 'Office'), 'com_size_value' => 1200, 'com_size_unit' => 'sqft', 'com_parking_spaces' => 4,
    'com_condition' => 'new', 'com_furnishing' => 'furnished', 'com_floor_number' => 11, 'com_total_floors' => 18, 'com_power_phase' => 'three', 'com_zoning' => 'commercial',
    'has_service_charge' => '1', 'service_charge_amount' => '30', 'service_charge_period' => 'month', 'service_charge_notes' => 'KSh 30 / ft² / month',
    'features' => demo_feats('commercial', ['Raised Floors', 'Cabled / Structured Network', 'Boardroom', 'Reception Area', 'Backup Generator', 'Elevator / Lift', 'Fire Sprinkler System', 'Visitor Parking']),
    'pms_property_id' => 'PMS-PROP-0030', 'pms_unit_id' => 'VBP-11A',
    'packages' => [
      ['name' => '3-year lease', 'package_type' => 'lease', 'total_price' => '6048000', 'deposit_amount' => '504000', 'duration_months' => '36', 'description' => '3 months rent-free fit-out period.', 'is_recommended' => '1'],
      ['name' => '1-year lease', 'package_type' => 'lease', 'total_price' => '2016000', 'deposit_amount' => '504000', 'duration_months' => '12'],
    ],
  ]],
];
foreach ($officialListings as [$kinds, $in]) {
    $in += ['is_negotiable' => '0'];
    $l = save_listing($in, $officialUser, ['official' => true, 'local_files' => demo_images($IMG, $kinds, 6)]);
    q("UPDATE listings SET views_count = ?, published_at = NOW() - INTERVAL ? DAY WHERE id = ?", [random_int(600, 2500), random_int(0, 5), $l['id']]);
}

echo "• favourites, chats, notifications\n";
$created = array_map(fn($l) => find_listing($l['uuid']), $created);
$buyer = $users['Wanjiru'];
foreach ([$created[0], $created[1], $created[9]] as $l) q("INSERT IGNORE INTO favourites (user_id, listing_id) VALUES (?, ?)", [$buyer, $l['id']]);
$conv = start_conversation($created[1], $U($buyer));
send_message($conv, $buyer, 'Hi Brian, is the Kilimani apartment still available? Is the price negotiable?');
send_message(one("SELECT * FROM conversations WHERE id = ?", [$conv['id']]), $users['Brian'], 'Hello Wanjiru! Yes it is available. We can discuss on price after a viewing — are you free Saturday?');
send_message(one("SELECT * FROM conversations WHERE id = ?", [$conv['id']]), $buyer, 'Saturday 11am works. Here is my pin: https://maps.google.com/?q=Kilimani');
$conv2 = start_conversation($created[11], $U($buyer));
send_message($conv2, $buyer, 'Hello, do you handle borehole pump repairs in Ruaka?');

foreach ([$created[0], $created[1], $created[2], $created[9]] as $l) {
    q("INSERT IGNORE INTO listing_stats_daily (listing_id, stat_date, views) VALUES (?, CURDATE(), ?)", [$l['id'], random_int(10, 80)]);
}
echo "  logins: official@vikao.io / Vikao@2026 · pms@vikao.io / Vikao@2026 · amina|brian|wanjiru|otieno|kevin@demo.vikao.io / $pass\n";
