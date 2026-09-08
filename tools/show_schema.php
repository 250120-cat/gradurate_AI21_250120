<?php
require_once __DIR__ . '/../db.php';
$pdo = get_pdo();

function showCols($pdo, $table){
    echo "=== $table ===\n";
    $rows = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r){
        echo $r['Field'] . "\t" . $r['Type'] . "\n";
    }
    echo "\n";
}

showCols($pdo,'news_cache');
showCols($pdo,'news_feeds');
