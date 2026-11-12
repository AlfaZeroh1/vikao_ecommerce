<?php
/**
 * Listing photo uploads: validation (type sniffing, size), re-encode + thumbnail via GD.
 */

/** Normalise $_FILES['photos'] (multiple) into a list. */
function uploaded_files(string $field): array {
    if (empty($_FILES[$field])) return [];
    $f = $_FILES[$field];
    if (!is_array($f['name'])) return $f['error'] === UPLOAD_ERR_NO_FILE ? [] : [$f];
    $out = [];
    foreach ($f['name'] as $i => $name) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $out[] = ['name' => $name, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

/** Validate list of uploads; returns error strings. */
function validate_photos(array $files): array {
    $errors = [];
    $max = config('uploads.max_bytes'); $mimes = config('uploads.mimes');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    foreach ($files as $f) {
        $n = e($f['name']);
        if ($f['error'] !== UPLOAD_ERR_OK) { $errors[] = "$n failed to upload (code {$f['error']})."; continue; }
        if ($f['size'] > $max) { $errors[] = "$n is larger than 5 MB."; continue; }
        $mime = $finfo->file($f['tmp_name']);
        if (!isset($mimes[$mime])) { $errors[] = "$n is not a JPG, PNG or WEBP image."; continue; }
        if (!@getimagesize($f['tmp_name'])) $errors[] = "$n could not be read as an image.";
    }
    return $errors;
}

/** Store one validated upload (or local file path) for a listing; returns media id. */
function store_listing_photo(int $listingId, array $file, int $sort, bool $isLocalCopy = false): int {
    $dir = rtrim(config('uploads.dir'), '/') . '/' . date('Y/m');
    if (!is_dir($dir)) mkdir($dir, 0775, true);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $ext = config('uploads.mimes')[$mime] ?? 'jpg';
    $base = $listingId . '-' . bin2hex(random_bytes(8));
    $rel = trim(config('uploads.url'), '/') . '/' . date('Y/m') . "/$base.$ext";
    $relThumb = trim(config('uploads.url'), '/') . '/' . date('Y/m') . "/$base-thumb.jpg";
    $dest = $dir . "/$base.$ext"; $destThumb = $dir . "/$base-thumb.jpg";

    [$w, $h] = getimagesize($file['tmp_name']) ?: [null, null];
    $ok = $isLocalCopy ? copy($file['tmp_name'], $dest) : move_uploaded_file($file['tmp_name'], $dest);
    if (!$ok) throw new RuntimeException('Could not save image.');
    make_thumb($dest, $mime, $destThumb, (int)config('uploads.thumb_width', 640));

    return insert('listing_media', [
        'listing_id' => $listingId, 'media_type' => 'image', 'storage' => 'local',
        'path' => $rel, 'thumb_path' => is_file($destThumb) ? $relThumb : $rel,
        'mime_type' => $mime, 'size_bytes' => filesize($dest), 'width' => $w, 'height' => $h, 'sort_order' => $sort,
    ]);
}

function make_thumb(string $src, string $mime, string $dest, int $tw): void {
    if (!function_exists('imagecreatetruecolor')) return;
    $img = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($src),
        'image/png'  => @imagecreatefrompng($src),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false,
        default => false,
    };
    if (!$img) return;
    $w = imagesx($img); $h = imagesy($img);
    $th = (int)round($h * ($tw / max(1, $w)));
    if ($w <= $tw) { $tw = $w; $th = $h; }
    $t = imagecreatetruecolor($tw, $th);
    imagefill($t, 0, 0, imagecolorallocate($t, 255, 255, 255));
    imagecopyresampled($t, $img, 0, 0, 0, 0, $tw, $th, $w, $h);
    imagejpeg($t, $dest, 80);
    imagedestroy($img); imagedestroy($t);
}

function delete_media_files(array $media): void {
    if (($media['storage'] ?? 'local') !== 'local') return;
    foreach (['path', 'thumb_path'] as $k) {
        $p = APP_ROOT . '/' . ltrim((string)$media[$k], '/');
        if ($media[$k] && is_file($p)) @unlink($p);
    }
}

function refresh_cover(int $listingId): void {
    $cover = val("SELECT id FROM listing_media WHERE listing_id = ? AND media_type = 'image' ORDER BY sort_order, id LIMIT 1", [$listingId]);
    q("UPDATE listings SET cover_media_id = ? WHERE id = ?", [$cover ?: null, $listingId]);
}
