<?php
/**
 * Thin PDO layer. Always prepared statements.
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = config('db');
    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => true,   // allows re-using named params
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ]);
    $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement {
    $st = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $name = is_int($k) ? $k + 1 : (str_starts_with($k, ':') ? $k : ":$k");
        $type = is_int($v) ? PDO::PARAM_INT : (is_null($v) ? PDO::PARAM_NULL : (is_bool($v) ? PDO::PARAM_INT : PDO::PARAM_STR));
        $st->bindValue($name, is_bool($v) ? (int)$v : $v, $type);
    }
    $st->execute();
    return $st;
}
function one(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function all(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function val(string $sql, array $p = []) { $r = q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }
function last_id(): int { return (int)db()->lastInsertId(); }

function insert(string $table, array $data): int {
    $cols = array_keys($data);
    $sql = "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES (:" . implode(',:', $cols) . ")";
    q($sql, $data);
    return last_id();
}
function update(string $table, array $data, string $where, array $whereParams = []): int {
    $set = [];
    foreach ($data as $k => $v) $set[] = "`$k` = :set_$k";
    $params = [];
    foreach ($data as $k => $v) $params["set_$k"] = $v;
    return q("UPDATE `$table` SET " . implode(', ', $set) . " WHERE $where", $params + $whereParams)->rowCount();
}

function tx(callable $fn) {
    $pdo = db();
    if ($pdo->inTransaction()) return $fn();
    $pdo->beginTransaction();
    try { $r = $fn(); $pdo->commit(); return $r; }
    catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}
