<?php
require_once __DIR__ . '/../db.php';
$pdo = get_pdo();
$stmt = $pdo->query('SELECT item_id, title, title_translated_ja, summary_translated_ja FROM news_cache LIMIT 10');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "id={$r['item_id']} title_translated_len=" . strlen($r['title_translated_ja']) . " title_translated_preview=" . mb_substr($r['title_translated_ja'],0,60) . "\n";
}
