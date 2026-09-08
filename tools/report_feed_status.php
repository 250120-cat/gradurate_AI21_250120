<?php
require_once __DIR__ . '/../db.php';
$pdo = get_pdo();
try {
    $sql = "SELECT f.feed_id, f.title, f.url, f.last_fetched, f.last_error, 
        (SELECT COUNT(*) FROM news_cache c WHERE c.feed_id = f.feed_id) AS cached_count
        FROM news_feeds f ORDER BY f.feed_id";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo "No feeds found\n";
        exit;
    }
    foreach ($rows as $r) {
        echo sprintf("feed_id=%s title=%s url=%s\n  last_fetched=%s\n  last_error=%s\n  cached_count=%s\n",
            $r['feed_id'], $r['title'], $r['url'], $r['last_fetched'] ?: 'NULL',
            ($r['last_error'] ? mb_strimwidth($r['last_error'], 0, 200, '...') : 'NULL'),
            $r['cached_count']
        );
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
