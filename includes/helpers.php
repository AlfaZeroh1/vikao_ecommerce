<?php
/**
 * General helpers: escaping, URLs, formatting, CSRF, JSON responses, sanitising.
 */

function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function base_path(): string {
    static $bp = null;
    if ($bp !== null) return $bp;
    $cfg = config('app.base_path');
    if ($cfg !== null) return $bp = rtrim($cfg, '/');
    $doc = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $root = realpath(APP_ROOT) ?: APP_ROOT;
    $bp = ($doc && str_starts_with($root, $doc)) ? str_replace('\\', '/', substr($root, strlen($doc))) : '';
    return $bp = rtrim($bp, '/');
}
function url(string $path = '', array $query = []): string {
    $u = base_path() . '/' . ltrim($path, '/');
    $query = array_filter($query, fn($v) => $v !== null && $v !== '' && $v !== []);
    return $query ? $u . '?' . http_build_query($query) : $u;
}
function asset(string $path): string {
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}
function media_url(?string $path): string {
    if (!$path) return asset('img/placeholder.svg');
    if (preg_match('#^https?://#', $path)) return $path;
    return url($path);
}
function redirect(string $to): never { header('Location: ' . (preg_match('#^https?://#', $to) ? $to : url($to))); exit; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function is_ajax(): bool { return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest' || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'); }

function input(string $key, $default = null) {
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}
function input_int(string $key, ?int $default = null): ?int {
    $v = input($key);
    return ($v === null || $v === '' || !is_numeric($v)) ? $default : (int)$v;
}
function input_float(string $key, ?float $default = null): ?float {
    $v = input($key);
    if (is_string($v)) $v = str_replace([',', ' '], '', $v);
    return ($v === null || $v === '' || !is_numeric($v)) ? $default : (float)$v;
}
function input_array(string $key): array {
    $v = $_POST[$key] ?? $_GET[$key] ?? [];
    if (is_string($v)) $v = $v === '' ? [] : explode(',', $v);
    return array_values(array_filter(array_map(fn($x) => is_string($x) ? trim($x) : $x, (array)$v), fn($x) => $x !== '' && $x !== null));
}

/* ---------------- JSON ---------------- */
function json_out(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function json_ok(array $data = [], string $message = 'OK'): never { json_out(['ok' => true, 'message' => $message] + $data); }
function json_fail(string $message, int $status = 422, array $errors = []): never { json_out(['ok' => false, 'message' => $message, 'errors' => $errors], $status); }

/* ---------------- CSRF ---------------- */
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function verify_csrf(): void {
    $t = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        if (is_ajax() || str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) json_fail('Your session expired. Refresh the page and try again.', 419);
        http_response_code(419); exit('Session expired. Go back and refresh.');
    }
}

/* ---------------- flash ---------------- */
function flash(string $type, string $msg): void { $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg]; }
function take_flashes(): array { $f = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $f; }

/* ---------------- ids ---------------- */
function uuid4(): string {
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}
function random_code(int $len = 6, string $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'): string {
    $s = ''; for ($i = 0; $i < $len; $i++) $s .= $alphabet[random_int(0, strlen($alphabet) - 1)]; return $s;
}
function slugify(string $s, int $max = 150): string {
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s), '-'));
    return substr($s ?: 'listing', 0, $max);
}

/* ---------------- formatting ---------------- */
function money($amount, string $currency = 'KES', bool $short = false): string {
    if ($amount === null || $amount === '') return 'Contact for price';
    $sym = ['KES' => 'KSh', 'UGX' => 'USh', 'TZS' => 'TSh', 'RWF' => 'RF', 'USD' => '$'][$currency] ?? $currency;
    $a = (float)$amount;
    if ($short) {
        if ($a >= 1e9) return $sym . ' ' . rtrim(rtrim(number_format($a / 1e9, 2), '0'), '.') . 'B';
        if ($a >= 1e6) return $sym . ' ' . rtrim(rtrim(number_format($a / 1e6, 2), '0'), '.') . 'M';
        if ($a >= 1e4) return $sym . ' ' . rtrim(rtrim(number_format($a / 1e3, 1), '0'), '.') . 'K';
    }
    return $sym . ' ' . number_format($a, ($a == floor($a)) ? 0 : 2);
}
function time_ago(?string $dt): string {
    if (!$dt) return '';
    $d = time() - strtotime($dt);
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . ' min ago';
    if ($d < 86400) return floor($d / 3600) . ' h ago';
    if ($d < 604800) return floor($d / 86400) . ' d ago';
    return date('j M Y', strtotime($dt));
}
function fdate(?string $dt, string $fmt = 'j M Y'): string { return $dt ? date($fmt, strtotime($dt)) : '—'; }
function initials(string $name): string {
    $p = preg_split('/\s+/', trim($name)); return strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr($p[1] ?? '', 0, 1));
}
function plural(int $n, string $one, ?string $many = null): string { return $n . ' ' . ($n === 1 ? $one : ($many ?? $one . 's')); }

function normalize_phone(?string $phone, string $cc = '254'): ?string {
    if (!$phone) return null;
    $p = preg_replace('/\D+/', '', $phone);
    if ($p === '') return null;
    if (str_starts_with($p, '0')) $p = $cc . substr($p, 1);
    elseif (strlen($p) === 9) $p = $cc . $p;
    return (strlen($p) >= 11 && strlen($p) <= 13) ? '+' . $p : null;
}
function mask_phone(?string $p): string { return $p ? substr($p, 0, 7) . ' *** ' . substr($p, -3) : ''; }

function youtube_id(?string $url): ?string {
    if (!$url) return null;
    if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) return $m[1];
    return null;
}

/* ---------------- HTML sanitising (Quill output) ---------------- */
function sanitize_rich_text(string $html): string {
    $allowed = '<p><br><strong><b><em><i><u><s><ul><ol><li><h2><h3><blockquote><a>';
    $html = strip_tags($html, $allowed);
    // drop every attribute except safe href on <a>
    $html = preg_replace_callback('/<(\/?)(\w+)([^>]*)>/', function ($m) {
        [$all, $close, $tag, $attrs] = $m;
        $tag = strtolower($tag);
        if ($close) return "</$tag>";
        if ($tag === 'a' && preg_match('/href\s*=\s*"([^"]*)"/i', $attrs, $h)) {
            $href = html_entity_decode($h[1]);
            if (!preg_match('#^(https?://|mailto:|tel:)#i', $href)) return '<a>';
            return '<a href="' . e($href) . '" target="_blank" rel="nofollow noopener">';
        }
        return "<$tag>";
    }, $html);
    return trim($html);
}
function rich_to_text(string $html): string {
    $t = preg_replace('/<(br|\/p|\/li|\/h\d)>/i', "\n", $html);
    return trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8')));
}
function linkify(string $text): string {
    $safe = e($text);
    $safe = preg_replace('~(https?://[^\s<]+)~i', '<a href="$1" target="_blank" rel="nofollow noopener" class="underline break-all">$1</a>', $safe);
    return nl2br($safe);
}
function excerpt(?string $text, int $len = 140): string {
    $t = trim(preg_replace('/\s+/', ' ', (string)$text));
    return mb_strlen($t) > $len ? mb_substr($t, 0, $len - 1) . '…' : $t;
}

/* ---------------- rate limit ---------------- */
function rate_limit(string $bucket, int $max, int $windowSeconds): bool {
    $row = one("SELECT hits, window_ends FROM rate_limits WHERE bucket = ?", [$bucket]);
    if (!$row || strtotime($row['window_ends']) < time()) {
        q("REPLACE INTO rate_limits (bucket, hits, window_ends) VALUES (?, 1, ?)", [$bucket, date('Y-m-d H:i:s', time() + $windowSeconds)]);
        return true;
    }
    if ((int)$row['hits'] >= $max) return false;
    q("UPDATE rate_limits SET hits = hits + 1 WHERE bucket = ?", [$bucket]);
    return true;
}

function client_ip(): string { return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45); }

function log_activity(?int $userId, string $action, ?string $subjectType = null, ?int $subjectId = null, array $meta = []): void {
    try {
        insert('activity_log', [
            'user_id' => $userId, 'action' => $action, 'subject_type' => $subjectType, 'subject_id' => $subjectId,
            'meta' => $meta ? json_encode($meta) : null, 'ip_address' => client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (Throwable $e) { /* never break a request for logging */ }
}

/** Render a component from includes/components with local vars. */
function component(string $name, array $vars = []): void {
    extract($vars, EXTR_SKIP);
    include APP_ROOT . "/includes/components/$name.php";
}
function render_component(string $name, array $vars = []): string { ob_start(); component($name, $vars); return ob_get_clean(); }
