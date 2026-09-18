<?php
require_once __DIR__ . '/config.php';
echo 'HOST: ' . DB_HOST . '<br>';
echo 'DB: ' . DB_NAME . '<br>';

function get_pdo() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
        } catch (PDOException $e) {
            echo 'DB接続エラー: ' . htmlspecialchars($e->getMessage());
            exit;
        }
    }
    return $pdo;
}
