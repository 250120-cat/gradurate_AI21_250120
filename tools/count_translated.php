<?php
require_once __DIR__ . '/../db.php';
$pdo = get_pdo();
try {
    $n = $pdo->query("SELECT COUNT(*) FROM news_cache WHERE title_translated_ja IS NOT NULL AND title_translated_ja <> ''")->fetchColumn();
    echo "translated_titles=" . $n . "\n";
    $m = $pdo->query("SELECT COUNT(*) FROM news_cache")->fetchColumn();
    echo "total_items=" . $m . "\n";
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
