<?php
require_once __DIR__ . '/../db.php';
$pdo = get_pdo();

echo "=== recent news_cache (latest 30) ===\n";
$stmt = $pdo->query("SELECT item_id, feed_id, title, link, pub_date, title_translated_ja, fetched_at FROM news_cache ORDER BY fetched_at DESC LIMIT 30");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $has_trans = !empty($r['title_translated_ja']) ? 'yes' : 'no';
    echo sprintf("item_id=%s feed=%s fetched_at=%s\n title=%s\n link=%s\n translated=%s\n\n",
        $r['item_id'],$r['feed_id'],$r['fetched_at'], mb_strimwidth($r['title'],0,140,'...'), $r['link'], $has_trans);
}

echo "=== news_feeds status ===\n";
$stmt = $pdo->query("SELECT feed_id, url, last_fetched, last_error FROM news_feeds ORDER BY feed_id");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo sprintf("feed_id=%s url=%s\n last_fetched=%s\n last_error=%s\n\n",
    $r['feed_id'], $r['url'], $r['last_fetched'] ?? 'NULL', mb_strimwidth($r['last_error'] ?? '',0,200,'...'));
}

echo "=== counts ===\n";
$c = $pdo->query("SELECT COUNT(*) FROM news_cache")->fetchColumn();
$cf = $pdo->query("SELECT COUNT(*) FROM news_feeds")->fetchColumn();
echo "news_cache: $c\nnews_feeds: $cf\n";
