<?php
require_once __DIR__ . '/../db.php';
$pdo = get_pdo();
try {
    $n = $pdo->query('SELECT COUNT(*) FROM news_feeds')->fetchColumn();
    echo "feeds=" . $n . "\n";
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
