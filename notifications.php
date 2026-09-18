<?php
require_once __DIR__ . '/functions.php';

require_login();

$page_title = '通知';
$page_heading = '通知';
$page_description = 'Pet Digital ID の通知一覧です。';

include __DIR__ . '/templates/header.php';
?>

<div class="card">
    <h2>🔔 通知</h2>

    <p>現在、新しい通知はありません。</p>
</div>

<?php
include __DIR__ . '/templates/footer.php';
?>