<?php
require_once __DIR__ . '/../db.php';
$pdo = get_pdo();
try {
    $stmt = $pdo->query('SELECT nf.feed_id, nf.title, nf.last_fetched, nf.last_error, COUNT(nc.item_id) AS cached_count FROM news_feeds nf LEFT JOIN news_cache nc ON nf.feed_id = nc.feed_id GROUP BY nf.feed_id');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $err = isset($r['last_error']) && $r['last_error'] !== null ? mb_substr($r['last_error'], 0, 120) : '';
        echo $r['feed_id'] . "\t" . ($r['title'] ?: 'untitled') . "\t" . ($r['last_fetched'] ?: 'NULL') . "\t" . ($err ?: '-') . "\t" . ($r['cached_count'] ?: 0) . "\n";
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
