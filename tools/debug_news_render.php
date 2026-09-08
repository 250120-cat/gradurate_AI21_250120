<?php
require_once __DIR__ . '/../functions.php';
$pdo = get_pdo();
$_GET['lang'] = 'ja';
$selected_lang = $_GET['lang'] ?? 'all';
$items = $pdo->query('SELECT nc.*, nf.title AS feed_title FROM news_cache nc LEFT JOIN news_feeds nf ON nc.feed_id = nf.feed_id ORDER BY nc.pub_date DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
function detect_lang_local($text){
    if (!$text) return 'en';
    if (preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}]/u', $text)) return 'ja';
    return 'en';
}
$norm = [];
foreach ($items as $it){
    $title = $it['title'] ?? '';
    $summary = $it['summary'] ?? '';
    $detected = detect_lang_local($title . ' ' . $summary);
    $feed_id = isset($it['feed_id']) ? (int)$it['feed_id'] : 0;
    if ($selected_lang === 'en' && $detected !== 'en') continue;
    $norm[] = [
        'item_id' => $it['item_id'] ?? null,
        'title' => $title,
        'summary' => $summary,
        'lang' => $detected,
        'title_translated' => $it['title_translated_ja'] ?? null,
        'summary_translated' => $it['summary_translated_ja'] ?? null,
    ];
}
echo "norm_count=" . count($norm) . "\n";
for ($i=0;$i<min(8,count($norm));$i++){
    $e = $norm[$i];
    echo sprintf("%d id=%s lang=%s translated_len=%d title=%s\n", $i+1, $e['item_id'], $e['lang'], strlen($e['title_translated'] ?? ''), mb_substr($e['title'],0,80));
}
// Now simulate translation step in news.php
if ($selected_lang === 'ja' && !empty($norm)) {
    foreach ($norm as &$entry) {
        if ($entry['lang'] === 'ja') continue;
        if (empty($entry['item_id'])) continue;
        $stmt = $pdo->prepare('SELECT title_translated_ja, summary_translated_ja FROM news_cache WHERE item_id = ? LIMIT 1');
        $stmt->execute([$entry['item_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $t_title = $row['title_translated_ja'] ?? null;
        $t_summary = $row['summary_translated_ja'] ?? null;
        if ($t_title) {
            $entry['title'] = $t_title;
        }
        if ($t_summary) {
            $entry['summary'] = $t_summary;
        }
    }
    unset($entry);
}

echo "after translate sample:\n";
for ($i=0;$i<min(8,count($norm));$i++){
    $e = $norm[$i];
    echo sprintf("%d id=%s lang=%s title_preview=%s\n", $i+1, $e['item_id'], $e['lang'], mb_substr($e['title'],0,80));
}
