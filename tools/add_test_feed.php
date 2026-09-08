<?php
require_once __DIR__ . '/../db.php';
$pdo = get_pdo();
$url = 'https://feeds.bbci.co.uk/news/rss.xml';
$title = 'Test BBC News';
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM news_feeds WHERE url = ?');
    $stmt->execute([$url]);
    if ($stmt->fetchColumn() == 0) {
        $ins = $pdo->prepare('INSERT INTO news_feeds (url, title, enabled, status) VALUES (?, ?, 1, 1)');
        $ins->execute([$url, $title]);
        echo "Inserted feed id=" . $pdo->lastInsertId() . "\n";
    } else {
        echo "Feed already exists: $url\n";
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
