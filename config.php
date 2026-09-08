<?php
// DB 接続設定を環境に合わせて編集してください
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'pet_digital_id');
define('DB_USER', 'root');
define('DB_PASS', '');

// ベースURL（必要に応じて設定）
define('BASE_URL', '/動物保護');

// メール通知設定
function env($key, $default = null) {
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }
    return $default;
}

if (!defined('MAIL_FROM')) define('MAIL_FROM', env('MAIL_FROM', 'noreply@example.com'));
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', env('ADMIN_EMAIL', 'admin@example.com'));

// SMTP設定（PHPMailer利用時）
// 実運用では環境変数で上書きしてください。例:
// SMTP_HOST=smtp.gmail.com
// SMTP_PORT=587
// SMTP_USER=your.email@gmail.com
// SMTP_PASS=your_app_password
// SMTP_SECURE=tls
// MAIL_FROM=your.email@gmail.com
// ADMIN_EMAIL=notify@example.com

// 開発用に MailHog を使う（ローカル受信、本番SMTP未設定時）
if (!defined('SMTP_HOST')) define('SMTP_HOST', env('SMTP_HOST', '127.0.0.1'));
if (!defined('SMTP_PORT')) define('SMTP_PORT', (int) env('SMTP_PORT', 1025));
if (!defined('SMTP_USER')) define('SMTP_USER', env('SMTP_USER', ''));
if (!defined('SMTP_PASS')) define('SMTP_PASS', env('SMTP_PASS', ''));
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', env('SMTP_SECURE', ''));
if (!defined('MAIL_FROM')) define('MAIL_FROM', env('MAIL_FROM', 'noreply@local'));
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', env('ADMIN_EMAIL', ADMIN_EMAIL));

// 注意:
// - セキュリティのため、実運用ではパスワードは環境変数や安全なストアから読み込むこと。
// - このファイルを公開リポジトリに置かないでください。

// プロトタイプ用管理者アカウント（本番ではDB化か環境変数を推奨）
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'adminpass'); // プロトタイプ用の平文パスワード。公開リポジトリに置かないでください。

// 開発中のログインデバッグ（falseにして本番相当の挙動に戻します）
if (!defined('DEBUG_LOGIN')) define('DEBUG_LOGIN', false);

ini_set('display_errors', 1);
error_reporting(E_ALL);

?>