<?php
require_once __DIR__ . '/functions.php';

$page_title = 'ログイン';
$page_heading = 'ログイン';
$page_description = '管理者・飼い主・ペットショップ・動物病院のいずれかが利用できるログインページです。';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        $error = '不正なリクエストです。もう一度お試しください。';
    } else {
        $user = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';
        if (attempt_login($user, $pass)) {
            $dest = safe_redirect_after_login($_SESSION['redirect_after_login'] ?? '');
            unset($_SESSION['redirect_after_login']);
            if (empty($dest) && current_user()['role'] === 'admin') {
                $dest = BASE_URL . '/admin/dashboard.php';
            }
            redirect($dest ?? BASE_URL . '/');
        } else {
            $error = 'ログインに失敗しました';
        }
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
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
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
    <p>飼い主アカウントはペットショップが発行します。ログインIDや仮パスワードが分からない場合は、ペットショップへお問い合わせください。</p>
  </div>
</div>
<?php include __DIR__ . '/templates/footer.php'; ?>
