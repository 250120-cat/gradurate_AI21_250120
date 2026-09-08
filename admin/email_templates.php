<?php
require_once __DIR__ . '/../functions.php';
require_login();

$user = current_user();
if (empty($user) || ($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$page_title = 'メールテンプレート管理';
$page_description = '迷子報告メールの件名と本文を編集できます。';

$key = $_GET['key'] ?? 'lost_found';
$message = '';
$message_type = 'notice';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        $message = '不正なリクエストです。';
        $message_type = 'alert';
    } else {
        $subject = $_POST['subject'] ?? '';
        $body_html = $_POST['body_html'] ?? '';
        $body_text = $_POST['body_text'] ?? '';
        if (save_email_template($key, $subject, $body_html, $body_text)) {
            $message = 'テンプレートを保存しました。';
        } else {
            $message = '保存に失敗しました。';
            $message_type = 'alert';
        }
    }
}

$tpl = load_email_template($key);
$subject = $tpl['subject'] ?? '';
$body_html = $tpl['body_html'] ?? '';
$body_text = $tpl['body_text'] ?? '';
?>
<?php include __DIR__ . '/../templates/header.php'; ?>
<div class="card">
  <h2>メールテンプレート編集</h2>
  <p>テンプレートキー: <strong><?php echo e($key); ?></strong></p>
  <?php if ($message): ?>
    <div class="<?php echo $message_type === 'alert' ? 'alert' : 'notice'; ?>">
      <?php echo e($message); ?>
    </div>
  <?php endif; ?>
  <form method="post" class="form-grid">
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
    <div class="form-group">
      <label>件名</label>
      <input type="text" name="subject" value="<?php echo e($subject); ?>">
    </div>
    <div class="form-group">
      <label>HTML本文 (プレースホルダ: {pet_name}, {code}, {reporter_name}, {reporter_phone}, {owner_name}, {notes}, {report_date}, {pet_link})</label>
      <textarea name="body_html" rows="10"><?php echo e($body_html); ?></textarea>
    </div>
    <div class="form-group">
      <label>プレーンテキスト本文</label>
      <textarea name="body_text" rows="10"><?php echo e($body_text); ?></textarea>
    </div>
    <button type="submit" class="button">保存</button>
  </form>
  <p><a class="button-link" href="<?php echo BASE_URL; ?>/admin">管理トップへ</a></p>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
