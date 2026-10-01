<?php
require_once __DIR__ . '/../functions.php';
$pdo = get_pdo();
try {
    $stmt = $pdo->query("SELECT item_id, title, summary FROM news_cache WHERE (title_translated_ja IS NULL OR title_translated_ja = '') OR (summary_translated_ja IS NULL OR summary_translated_ja = '') LIMIT 10");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo "No items to translate\n";
        exit;
    }
    foreach ($rows as $r) {
        $id = $r['item_id'];
        $title = $r['title'] ?? '';
        $summary = $r['summary'] ?? '';
        echo "Translating item_id={$id}... ";
        $t = translate_text($title, 'ja');
        $s = translate_text($summary, 'ja');
        $pdo->prepare('UPDATE news_cache SET title_translated_ja = ?, summary_translated_ja = ? WHERE item_id = ?')->execute([$t, $s, $id]);
        echo "done\n";
        usleep(200000);
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
