<?php
require_once __DIR__ . '/functions.php';

$page_title = 'ログイン';
$page_heading = 'ログイン';
$page_description = '管理者・飼い主・ペットショップ・動物病院のいずれかが利用できるログインページです。';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    if (attempt_login($user, $pass)){
        $dest = $_SESSION['redirect_after_login'] ?? null;
        unset($_SESSION['redirect_after_login']);
        if (empty($dest) && current_user()['role'] === 'admin') {
            $dest = BASE_URL . '/admin/dashboard.php';
        }
        redirect($dest ?? BASE_URL . '/');
    } else {
        $error = 'ログインに失敗しました';
    }
}
?>
<?php include __DIR__ . '/templates/header.php'; ?>
<div class="card">
  <?php if ($error): ?>
    <div class="errors">
      <ul>
        <li><?php echo e($error); ?></li>
      </ul>
    </div>
  <?php endif; ?>
  <form method="post" class="form-grid">
    <div class="form-group">
      <label>ユーザー名</label>
      <input type="text" name="username" required>
    </div>
    <div class="form-group">
      <label>パスワード</label>
      <input type="password" name="password" required>
    </div>
    <button type="submit" class="button">ログイン</button>
  </form>
  <div class="notice" style="margin-top:18px;">
    <p>管理者、飼い主、ペットショップ、動物病院のいずれのユーザーでもここからログインできます。</p>
    <p>新規登録は管理者画面または各部門の登録ページから行ってください。</p>
  </div>
</div>
<?php include __DIR__ . '/templates/footer.php'; ?>
