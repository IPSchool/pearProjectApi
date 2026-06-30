<?php
/**
 * HistoryV：将数据库中已过期的 vilson HTTPS 静态资源链接改为 HTTP
 * 幂等，可重复执行。
 */
$host = getenv('PEAR_DB_HOST') ?: 'mysql';
$port = getenv('PEAR_DB_PORT') ?: '3306';
$user = getenv('PEAR_DB_USER') ?: 'root';
$pass = getenv('PEAR_DB_PASS') ?: 'root';
$db = getenv('PEAR_DB_NAME') ?: 'pearproject';
$prefix = getenv('PEAR_DB_PREFIX') ?: 'pear_';

$pairs = [
    'https://static.vilson.xyz' => 'http://static.vilson.xyz',
    'https://static.vilson.online' => 'http://static.vilson.online',
    'https://beta.vilson.xyz' => 'http://beta.vilson.xyz',
    'https:\/\/static.vilson.xyz' => 'http:\/\/static.vilson.xyz',
    'https:\/\/static.vilson.online' => 'http:\/\/static.vilson.online',
    'https:\/\/beta.vilson.xyz' => 'http:\/\/beta.vilson.xyz',
];

$columns = [
    "{$prefix}member" => ['avatar'],
    "{$prefix}member_account" => ['avatar'],
    "{$prefix}notify" => ['send_data', 'avatar'],
    "{$prefix}task" => ['description'],
    "{$prefix}project" => ['cover'],
    "{$prefix}project_log" => ['content'],
];

try {
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Exception $e) {
    fwrite(STDERR, "[HistoryV] skip static url fix: DB unavailable\n");
    exit(0);
}

$total = 0;
foreach ($columns as $table => $fields) {
    foreach ($fields as $field) {
        foreach ($pairs as $from => $to) {
            $sql = "UPDATE `{$table}` SET `{$field}` = REPLACE(`{$field}`, :from, :to)
                    WHERE `{$field}` LIKE :like";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':from' => $from,
                ':to' => $to,
                ':like' => '%' . str_replace(['%', '_'], ['\\%', '\\_'], $from) . '%',
            ]);
            $total += $stmt->rowCount();
        }
    }
}

if ($total > 0) {
    echo "[HistoryV] Fixed {$total} vilson HTTPS static URL(s) in database.\n";
}
