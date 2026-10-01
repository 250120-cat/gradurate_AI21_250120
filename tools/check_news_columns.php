<?php
require_once __DIR__ . '/../db.php';
$pdo = get_pdo();
try {
    $stmt = $pdo->query('SHOW COLUMNS FROM news_feeds');
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo $c['Field'] . "\t" . $c['Type'] . "\n";
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
