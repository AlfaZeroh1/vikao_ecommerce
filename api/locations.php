<?php
require __DIR__ . '/_init.php';

route([
    // children of a location (or counties for country when parent empty)
    'children' => function () {
        $parent = input_int('parent');
        $country = input_int('country', 1);
        $items = $parent
            ? all("SELECT id, name, level FROM locations WHERE parent_id = ? AND is_active = 1 ORDER BY name", [$parent])
            : all("SELECT id, name, level FROM locations WHERE country_id = ? AND level = 1 AND is_active = 1 ORDER BY name", [$country]);
        header('Cache-Control: public, max-age=3600');
        json_ok(['items' => $items]);
    },
    // typeahead
    'search' => function () {
        $term = trim((string)input('q', ''));
        if (mb_strlen($term) < 2) json_ok(['items' => []]);
        $items = all("SELECT id, name, full_name, level FROM locations WHERE is_active = 1 AND (name LIKE ? OR full_name LIKE ?) ORDER BY is_popular DESC, level, name LIMIT 15", [$term . '%', '%' . $term . '%']);
        json_ok(['items' => $items]);
    },
]);
