<?php
require_once __DIR__ . '/functions.php';

require_login();
require_role('admin');

$page_title = 'サンプルデータ';
$page_heading = 'サンプルデータ';
$page_description = '開発・確認用のサンプルデータを管理します。';

include __DIR__ . '/templates/header.php';
?>

<div class="card">
    <h2>🧪 サンプルデータ</h2>
    <p>現在、サンプルデータ投入機能は準備中です。</p>

    <p>
        <a class="button-link" href="<?php echo BASE_URL; ?>/admin/dashboard.php">
            管理トップへ戻る
        </a>
    </p>
</div>

<?php
include __DIR__ . '/templates/footer.php';
?>