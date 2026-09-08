<?php
require_once __DIR__ . '/../db.php';
$pdo = get_pdo();
$key = 'animal_score_threshold';
$value = 1;
try{
    $stmt = $pdo->prepare('SELECT `value` FROM news_settings WHERE `key` = ? LIMIT 1');
    $stmt->execute([$key]);
    if ($stmt->fetch()){
        $u = $pdo->prepare('UPDATE news_settings SET `value` = ? WHERE `key` = ?');
        $u->execute([$value, $key]);
        echo "Restored threshold to $value\n";
    } else {
        $i = $pdo->prepare('INSERT INTO news_settings (`key`,`value`) VALUES (?,?)');
        $i->execute([$key,$value]);
        echo "Inserted threshold $value\n";
    }
} catch (Exception $e){
    echo "ERROR: " . $e->getMessage() . "\n";
}
