<?php
require_once __DIR__ . '/../functions.php';
$pdo = get_pdo();
$items = $pdo->query('SELECT nc.*, nf.title AS feed_title FROM news_cache nc LEFT JOIN news_feeds nf ON nc.feed_id = nf.feed_id ORDER BY nc.pub_date DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
$counts = ['total'=>count($items),'all'=>0,'ja'=>0,'en'=>0];
function detect_lang_local($text){
    if (!$text) return 'en';
    if (preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}]/u', $text)) return 'ja';
    return 'en';
}
foreach ($items as $it){
    $title = $it['title'] ?? '';
    $summary = $it['summary'] ?? '';
    $det = detect_lang_local($title . ' ' . $summary);
    $counts['all']++;
    if ($det === 'ja') $counts['ja']++;
    if ($det === 'en') $counts['en']++;
}
echo "counts: total={$counts['total']} ja={$counts['ja']} en={$counts['en']}\n";
// Now emulate news.php filtering for selected_lang=ja
$selected_lang = 'ja';
$passed = 0;
foreach ($items as $it){
    $title = $it['title'] ?? '';
    $summary = $it['summary'] ?? '';
    $det = detect_lang_local($title . ' ' . $summary);
    // feed filter (none)
    if ($selected_lang === 'en' && $det !== 'en') continue;
    // keyword none
    $passed++;
}
echo "passed when selected_lang=ja: $passed\n";
$selected_lang = 'en';
$passed2 = 0;
foreach ($items as $it){
    $title = $it['title'] ?? '';
    $summary = $it['summary'] ?? '';
    $det = detect_lang_local($title . ' ' . $summary);
    if ($selected_lang === 'en' && $det !== 'en') continue;
    $passed2++;
}
echo "passed when selected_lang=en: $passed2\n";
