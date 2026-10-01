<?php
require_once __DIR__ . '/functions.php';
require_login();

$page_title = 'パスワード変更';
$page_heading = 'パスワード変更';
$page_description = 'ログイン中のアカウントのパスワードを更新します。';
$message = null;
$message_type = 'notice';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        http_response_code(400);
        $message = '不正な操作が検出されました。画面を再読み込みしてください。';
        $message_type = 'alert';
    } else {
        $user = current_user();
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($user['id'])) {
            $message = 'このログインではパスワード変更できません。データベースに登録されたユーザーでログインしてください。';
            $message_type = 'alert';
        } elseif (strlen($new_password) < 10) {
            $message = '新しいパスワードは10文字以上にしてください。';
            $message_type = 'alert';
        } elseif ($new_password !== $confirm_password) {
            $message = '新しいパスワードと確認用パスワードが一致しません。';
            $message_type = 'alert';
        } else {
            $pdo = get_pdo();
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ? LIMIT 1');
            $stmt->execute([(int)$user['id']]);
            $password_hash = $stmt->fetchColumn();

            if (!$password_hash || !password_verify($current_password, $password_hash)) {
                $message = '現在のパスワードが正しくありません。';
                $message_type = 'alert';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
                $stmt->execute([password_hash($new_password, PASSWORD_DEFAULT), (int)$user['id']]);
                $_SESSION['user']['password_change_required'] = false;
                add_audit_log('password_changed', 'user', (int)$user['id'], '本人がパスワードを変更しました');
                $_SESSION['flash_message'] = 'パスワードを変更しました。';
                redirect(BASE_URL . '/change_password.php');
            }
        }
    }
}

$flash_message = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);
?>
<?php include __DIR__ . '/templates/header.php'; ?>
<div class="card">
  <?php if ($flash_message): ?>
    <div class="notice"><?php echo e($flash_message); ?></div>
  <?php endif; ?>
  <?php if ($message): ?>
    <div class="<?php echo $message_type === 'alert' ? 'alert' : 'notice'; ?>"><?php echo e($message); ?></div>
  <?php endif; ?>
  <form method="post" class="form-grid">
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
    <div class="form-group">
      <label for="current-password">現在のパスワード</label>
      <input id="current-password" type="password" name="current_password" autocomplete="current-password" required>
    </div>
    <div class="form-group">
      <label for="new-password">新しいパスワード（10文字以上）</label>
      <input id="new-password" type="password" name="new_password" minlength="10" autocomplete="new-password" required>
    </div>
    <div class="form-group">
      <label for="confirm-password">新しいパスワード（確認）</label>
      <input id="confirm-password" type="password" name="confirm_password" minlength="10" autocomplete="new-password" required>
    </div>
    <button type="submit" class="button">パスワードを変更</button>
  </form>
</div>
<?php include __DIR__ . '/templates/footer.php'; ?>
