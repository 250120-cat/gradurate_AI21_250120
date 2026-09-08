<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/../functions.php';

$to = 'sum1lemon.0422@gmail.com';
$subject = '【テスト】迷子報告メール送信テスト';
$body = "これは送信テストです。\n時刻: " . date('c') . "\n\nもしこのメールが届かない場合は、`config.php` の SMTP 設定を確認してください。";

echo "送信先: $to\n";

$sent = false;
try {
    $sent = send_email_notification($to, $subject, $body);
} catch (Throwable $e) {
    echo "例外が発生しました: " . $e->getMessage() . "\n";
}

if ($sent) {
    echo "メール送信が成功しました。\n";
    exit(0);
} else {
    echo "メール送信に失敗しました。\n";
    exit(1);
}
