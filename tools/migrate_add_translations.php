<?php
require_once __DIR__ . '/../db.php';
$pdo = get_pdo();
try {
    $cols = $pdo->query("SHOW COLUMNS FROM news_cache LIKE 'title_translated_ja'")->fetch();
    if (!$cols) {
        $pdo->exec("ALTER TABLE news_cache ADD COLUMN title_translated_ja TEXT NULL, ADD COLUMN summary_translated_ja TEXT NULL");
        echo "Added columns\n";
    } else {
        echo "Columns already exist\n";
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
