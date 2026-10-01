<?php
require_once __DIR__ . '/../db.php';
$pdo = get_pdo();
$keywords = [
    'dog','dogs','cat','cats','pet','pets','puppy','kitten','rabbit','hamster','fish','bird','parrot','vet','veterinary','rescue','shelter','adoption',
    '犬','猫','ペット','動物','保護','里親','里親募集','獣医','動物病院','迷子','保護団体','動物愛護'
];
try{
    $ins = $pdo->prepare('INSERT INTO news_keywords (keyword,type) VALUES (?,"allow")');
    $chk = $pdo->prepare('SELECT id FROM news_keywords WHERE keyword = ? LIMIT 1');
    $added = 0;
    foreach ($keywords as $k){
        $k = trim($k);
        if ($k === '') continue;
        $chk->execute([$k]);
        if ($chk->fetch()) continue;
        $ins->execute([$k]);
        $added++;
        echo "Inserted: $k\n";
    }
    echo "Done. Added: $added\n";
}catch(Exception $e){
    echo 'ERROR: '.$e->getMessage()."\n";
}
