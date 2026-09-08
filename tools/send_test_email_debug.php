<?php
require __DIR__ . '/../config.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    echo "vendor/autoload.php が見つかりません。ComposerでPHPMailerをインストールしてください。\n";
    exit(1);
}
require $autoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$to = defined('ADMIN_EMAIL') && ADMIN_EMAIL ? ADMIN_EMAIL : (defined('MAIL_FROM') ? MAIL_FROM : 'test@example.com');
$subject = '【デバッグ】PHPMailer SMTP テスト';
$body = "これは PHPMailer の SMTP デバッグ出力テストです。\n時刻: " . date('c') . "\n";

$mail = new PHPMailer(true);
try {
    // 詳細デバッグ出力
    $mail->SMTPDebug = SMTP::DEBUG_SERVER; // 2
    $mail->Debugoutput = function($str, $level) { echo $str . PHP_EOL; };

    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = !empty(SMTP_USER);
    if (!empty(SMTP_USER)) {
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
    }
    if (!empty(SMTP_SECURE)) {
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->SMTPAutoTLS = true;
    } else {
        $mail->SMTPAutoTLS = false;
    }
    $mail->Port = SMTP_PORT;

    $from = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@example.com';
    $mail->setFrom($from);
    $mail->addAddress($to);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->AltBody = strip_tags($body);

    $mail->send();
    echo "送信成功\n";
    exit(0);
} catch (Exception $e) {
    echo "送信エラー: " . $e->getMessage() . "\n";
    exit(1);
}
