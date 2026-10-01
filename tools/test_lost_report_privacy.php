<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/functions.php';

$input = implode("\n", [
    '【コード不明の写真報告】',
    '【発見者】試験ユーザー',
    '【連絡先】000-0000-0000',
    '場所: 公園',
    'メモ: 首輪なし',
]);
$publicNotes = public_lost_report_notes($input);

if (strpos($publicNotes, '試験ユーザー') !== false
    || strpos($publicNotes, '000-0000-0000') !== false
    || strpos($publicNotes, '公園') === false
    || strpos($publicNotes, '首輪なし') === false) {
    echo "FAIL: 公開メモの個人情報秘匿テスト\n";
    exit(1);
}

echo "PASS: 発見者情報を隠し、公開用のメモは保持します。\n";
