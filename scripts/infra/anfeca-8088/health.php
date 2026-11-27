<?php

declare(strict_types=1);

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $database = new PDO('pgsql:host=/run/anfeca8088-pg;port=55488;dbname=certificacion_anfeca', 'anfeca8088');
    $identity = $database->query("select current_database() db, current_user role, current_setting('server_version_num') pg")->fetch(PDO::FETCH_ASSOC);
    $ok = PHP_VERSION === '8.5.12' && PHP_SAPI === 'fpm-fcgi' && (int) $identity['pg'] === 180006
        && $identity['db'] === 'certificacion_anfeca' && $identity['role'] === 'anfeca8088';
    http_response_code($ok ? 200 : 503);
    echo json_encode(['ok' => $ok, 'php' => PHP_VERSION, 'sapi' => PHP_SAPI, 'database' => $identity]);
} catch (Throwable) {
    http_response_code(503);
    echo '{"ok":false}';
}
