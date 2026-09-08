<?php
require_once __DIR__ . '/db.php';
try{
    $pdo = get_pdo();
    $stmt = $pdo->query("SHOW COLUMNS FROM consultation_threads LIKE 'genre'");
    $res = $stmt->fetchAll();
    echo (count($res) > 0) ? 'ok' : 'missing';
} catch (Exception $e){
    echo 'err:'. $e->getMessage();
}
