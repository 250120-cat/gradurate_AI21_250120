<?php
require_once __DIR__ . '/../functions.php';
require_login(); require_role('admin');
$pdo = get_pdo();
$feed_id = isset($_GET['feed']) ? (int)$_GET['feed'] : null;
$feeds = [];
if ($feed_id) {
    $stmt = $pdo->prepare('SELECT * FROM news_feeds WHERE feed_id = ? AND enabled = 1');
    $stmt->execute([$feed_id]);
    $feeds = $stmt->fetchAll();
} else {
    $feeds = $pdo->query('SELECT * FROM news_feeds WHERE enabled = 1')->fetchAll();
}

function parse_feed_items($xml) {
    if (isset($xml->channel->item)) {
        return $xml->channel->item;
    }
    if (isset($xml->entry)) {
        return $xml->entry;
    }
    return [];
}

function get_feed_link($item) {
    if (isset($item->link) && (string) $item->link !== '') {
        if ($item->link->attributes()) {
            return (string) $item->link->attributes()->href;
        }
        return (string) $item->link;
    }
    if (isset($item->guid)) {
        return (string) $item->guid;
    }
    return '';
}

foreach ($feeds as $f){
    $url = $f['url'];
    try{
        $ctx = stream_context_create(['http'=>['timeout'=>10, 'user_agent' => 'Mozilla/5.0']]);
        $data = @file_get_contents($url, false, $ctx);
        if (!$data) {
            $pdo->prepare('UPDATE news_feeds SET last_error = ? WHERE feed_id = ?')->execute(['no data', $f['feed_id']]);
            continue;
        }
        $xml = @simplexml_load_string($data);
        if (!$xml) {
            $pdo->prepare('UPDATE news_feeds SET last_error = ? WHERE feed_id = ?')->execute(['invalid xml', $f['feed_id']]);
            continue;
        }
        $items = parse_feed_items($xml);
        if (count($items) === 0) {
            $pdo->prepare('UPDATE news_feeds SET last_fetched = NOW(), last_error = ? WHERE feed_id = ?')->execute(['no items', $f['feed_id']]);
            continue;
        }
        $inserted = 0;
        foreach ($items as $it){
            $title = trim((string)($it->title ?? $it->heading ?? ''));
            $link = trim(get_feed_link($it));
            $date = null;
            if (isset($it->pubDate)) {
                $date = date('Y-m-d H:i:s', strtotime((string)$it->pubDate));
            } elseif (isset($it->updated)) {
                $date = date('Y-m-d H:i:s', strtotime((string)$it->updated));
            } elseif (isset($it->published)) {
                $date = date('Y-m-d H:i:s', strtotime((string)$it->published));
            }
            $summary = '';
            if (isset($it->description)) {
                $summary = (string)$it->description;
            } elseif (isset($it->summary)) {
                $summary = (string)$it->summary;
            } elseif (isset($it->content)) {
                $summary = (string)$it->content;
            }
            if ($title === '' && $link === '') {
                continue;
            }
            // Prevent duplicates by link (same behavior as CLI fetcher)
            if ($link !== '') {
                $exists = $pdo->prepare('SELECT item_id FROM news_cache WHERE link = ? LIMIT 1');
                $exists->execute([$link]);
                if ($exists->fetch()) {
                    continue;
                }
            }
            $stmt = $pdo->prepare('INSERT INTO news_cache (feed_id, title, link, summary, pub_date) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$f['feed_id'], $title, $link, $summary, $date]);
            $inserted++;
        }
        if ($inserted > 0) {
            $pdo->prepare('UPDATE news_feeds SET last_fetched = NOW(), last_error = NULL WHERE feed_id = ?')->execute([$f['feed_id']]);
        } else {
            $pdo->prepare('UPDATE news_feeds SET last_fetched = NOW(), last_error = ? WHERE feed_id = ?')->execute(['no new items', $f['feed_id']]);
        }
    } catch (Exception $e){
        $msg = mb_substr($e->getMessage(), 0, 1000);
        $pdo->prepare('UPDATE news_feeds SET last_error = ? WHERE feed_id = ?')->execute([$msg, $f['feed_id']]);
    }
}
header('Location: ' . BASE_URL . '/admin/news_feeds.php');
exit;
