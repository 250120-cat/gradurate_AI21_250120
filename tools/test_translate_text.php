<?php
require_once __DIR__ . '/../functions.php';
$tests = ['Hello world', 'The quick brown fox', 'Jude Bellingham gives England the lead'];
foreach ($tests as $t) {
    echo "Translating: $t\n";
    $r = translate_text($t, 'ja');
    var_dump($r);
}
