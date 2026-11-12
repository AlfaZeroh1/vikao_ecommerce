<?php
/**
 * Vikao installer (CLI):  php database/install.php [--fresh] [--no-demo]
 *   --fresh    drop & recreate the database first
 *   --no-demo  skip demo users/listings
 *   --no-fulltext  skip FULLTEXT indexes (then set search.driver = 'like')
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Run from the command line.'); }
require __DIR__ . '/../includes/bootstrap.php';

$args = $argv ?? [];
$c = config('db');
$pdo = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (in_array('--fresh', $args, true)) { $pdo->exec("DROP DATABASE IF EXISTS `{$c['name']}`"); echo "• dropped {$c['name']}\n"; }

function run_sql_file(PDO $pdo, string $file, string $db, bool $noFulltext = false): void {
    $sql = file_get_contents($file);
    if ($noFulltext) $sql = preg_replace('/,\s*\n\s*FULLTEXT KEY[^\n]*?\)(?=\s*[,\n])/', '', $sql);
    $sql = str_replace(['CREATE DATABASE IF NOT EXISTS vikao', 'USE vikao;'], ["CREATE DATABASE IF NOT EXISTS `$db`", "USE `$db`;"], $sql);
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $n = 0;
    foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;
        $pdo->exec($stmt); $n++;
    }
    echo "• " . basename($file) . ": $n statements\n";
}
// --no-fulltext: for engines without FULLTEXT support (set search.driver = 'like')
run_sql_file($pdo, __DIR__ . '/schema.sql', $c['name'], in_array('--no-fulltext', $args, true));
run_sql_file($pdo, __DIR__ . '/seed.sql', $c['name']);

if (!in_array('--no-demo', $args, true)) {
    require __DIR__ . '/demo_seed.php';
}
echo "✔ Vikao database ready.\n";
