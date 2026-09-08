<?php
// CLI script to fetch news feeds into news_cache
if (php_sapi_name() !== 'cli') exit("Run from CLI only\n");
require_once __DIR__ . '/functions.php';
$pdo = get_pdo();
$feeds = $pdo->query('SELECT * FROM news_feeds WHERE enabled = 1')->fetchAll();
foreach ($feeds as $f){
    $url = $f['url'];
    try{
        $ctx = stream_context_create(['http'=>['timeout'=>10]]);
        $data = @file_get_contents($url, false, $ctx);
        if (!$data) continue;
        $xml = @simplexml_load_string($data);
        if (!$xml) continue;
        if (isset($xml->channel->item)){
            foreach ($xml->channel->item as $it){
                $title = (string)$it->title;
                $link = (string)$it->link;
                $date = isset($it->pubDate) ? date('Y-m-d H:i:s', strtotime((string)$it->pubDate)) : null;
                $summary = isset($it->description) ? (string)$it->description : null;
                // Prevent duplicates by link
                $exists = $pdo->prepare('SELECT item_id FROM news_cache WHERE link = ? LIMIT 1');
                $exists->execute([$link]);
                if ($exists->fetch()) continue;
                $stmt = $pdo->prepare('INSERT INTO news_cache (feed_id, title, link, summary, pub_date) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$f['feed_id'], $title, $link, $summary, $date]);
            }
        }
        $pdo->prepare('UPDATE news_feeds SET last_fetched = NOW() WHERE feed_id = ?')->execute([$f['feed_id']]);
        echo "Fetched feed: {$f['feed_id']}\n";
    } catch (Exception $e){
        echo "Error fetching {$f['feed_id']}: " . $e->getMessage() . "\n";
    }
}
echo "Done\n";
