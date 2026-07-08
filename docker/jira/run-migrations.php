<?php
/**
 * 幂等数据库补丁（Docker 启动 / restart-api / fixture-init 时执行）
 *
 * 1. 库默认字符集 → utf8mb4
 * 2. 所有 pear_* 表 CONVERT TO utf8mb4
 * 3. data/2.9.0/hero-utf8mb4-schema.sql 列级修正（mediumtext、varchar 扩容等）
 */
namespace think;

$rootPath = realpath(__DIR__ . '/../..') . DIRECTORY_SEPARATOR;
require $rootPath . 'vendor/autoload.php';
require_once $rootPath . 'app/common/common-gateb.php';

$app = new App();
$app->initialize();

use think\facade\Db;

function migrate_warn(string $msg): void
{
    echo "[migrate] WARN: {$msg}\n";
}

function migrate_ok(string $msg): void
{
    echo "[migrate] {$msg}\n";
}

function run_sql_file(string $path): void
{
    if (!is_file($path)) {
        migrate_warn("missing {$path}");
        return;
    }
    $sql = file_get_contents($path);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement === '' || stripos($statement, 'SET NAMES') === 0) {
            continue;
        }
        try {
            Db::execute($statement);
        } catch (\Throwable $e) {
            migrate_warn($e->getMessage());
        }
    }
}

$dbName = env('database.database', 'pearproject');
try {
    Db::execute(
        "ALTER DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    migrate_ok("database {$dbName} → utf8mb4");
} catch (\Throwable $e) {
    migrate_warn("database charset: {$e->getMessage()}");
}

$indexFixFile = $rootPath . 'data/2.9.0/hero-utf8mb4-index-fix.sql';
run_sql_file($indexFixFile);
migrate_ok('hero-utf8mb4-index-fix.sql applied');

$prefix = env('database.prefix', 'pear_');
$tables = Db::query('SHOW TABLES');
$converted = 0;
foreach ($tables as $row) {
    $table = array_values((array) $row)[0];
    if (!str_starts_with($table, $prefix)) {
        continue;
    }
    try {
        Db::execute(
            "ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
        $converted++;
    } catch (\Throwable $e) {
        migrate_warn("{$table}: {$e->getMessage()}");
    }
}
migrate_ok("converted {$converted} table(s) with prefix {$prefix}");

$schemaFile = $rootPath . 'data/2.9.0/hero-utf8mb4-schema.sql';
run_sql_file($schemaFile);
migrate_ok('hero-utf8mb4-schema.sql applied');

$llmConfigFile = $rootPath . 'data/2.9.0/system-llm-config.sql';
run_sql_file($llmConfigFile);
migrate_ok('system-llm-config.sql applied');

$resolutionFile = $rootPath . 'data/migrations/20260707_task_resolution.sql';
run_sql_file($resolutionFile);
migrate_ok('task-resolution migration applied');

$resolutionSyncFile = $rootPath . 'data/migrations/20260707_task_resolution_sync.sql';
run_sql_file($resolutionSyncFile);
migrate_ok('task-resolution-sync migration applied');

$jiraExtrasFile = $rootPath . 'data/migrations/20260708_jira_watcher_link_webhook_filter.sql';
run_sql_file($jiraExtrasFile);
migrate_ok('jira watcher/link/webhook/filter migration applied');
