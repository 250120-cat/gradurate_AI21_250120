<?php
require_once __DIR__ . '/db.php';
$pdo = get_pdo();
foreach (['consultation_genres','news_feeds','news_cache'] as $t){
    $r = $pdo->query("SHOW TABLES LIKE '" . $t . "'")->fetchAll();
    echo $t . ':' . (count($r) ? 'ok' : 'missing') . "\n";
}
